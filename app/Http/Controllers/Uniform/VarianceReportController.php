<?php

namespace App\Http\Controllers\Uniform;

use App\Exports\Uniform\VarianceReportDetailedExport;
use App\Exports\Uniform\VarianceReportExport;
use App\Http\Controllers\Controller;
use App\Models\UniformPhysicalCount;
use App\Models\UniformPhysicalCountItem;
use App\Models\UniformPeriodLock;
use App\Models\UniformVariance;
use App\Models\UniformVarianceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class VarianceReportController extends Controller
{
    public function variances(Request $request)
    {
        $query = $this->varianceQuery($request)->with('physicalCount', 'items.item.size', 'items.item.gender')->latest();

        return response()->json($query->paginate((int) $request->input('per_page', 10)));
    }

    public function exportVariances(Request $request)
    {
        return Excel::download(
            new VarianceReportExport($this->varianceQuery($request)->with('physicalCount')->latest()->get()),
            'uniform-variance-report.xls'
        );
    }

    public function exportVarianceDetails(int $id)
    {
        $variance = UniformVariance::with('physicalCount', 'items')->findOrFail($id);

        return Excel::download(
            new VarianceReportDetailedExport($variance),
            'variance-detailed-' . $variance->variance_no . '.xlsx'
        );
    }

    public function previewVariance(Request $request)
    {
        $data = $request->validate([
            'begin_date' => ['required', 'date'],
            'ending_date' => ['required', 'date', 'after_or_equal:begin_date'],
            'items' => ['sometimes', 'array'],
            'items.*.uniform_item_id' => ['required_with:items', 'integer', 'exists:uniform_items,id'],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'items.*.reason_of_adjustment' => ['nullable', 'string'],
        ]);

        $physicalCount = UniformPhysicalCount::with('items')
            ->where('status', 'Posted')
            ->whereDate('begin_date', $data['begin_date'])
            ->whereDate('ending_date', $data['ending_date'])
            ->first();

        if (!$physicalCount) {
            return response()->json([
                'message' => 'No posted physical count found for the selected date range.'
            ], 422);
        }

        return response()->json([
            'items' => $this->varianceLines(
                $physicalCount,
                $data['items'] ?? []
            ),
        ]);
    }
    public function storeVariance(Request $request)
{
    $data = $request->validate([
        'begin_date' => ['required', 'date'],
        'ending_date' => ['required', 'date', 'after_or_equal:begin_date'],
        'remarks' => ['nullable', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.uniform_item_id' => ['required', 'integer', 'distinct', 'exists:uniform_items,id'],
        'items.*.adjustment' => ['nullable', 'numeric'],
        'items.*.reason_of_adjustment' => ['nullable', 'string'],
    ]);

    return DB::transaction(function () use ($data) {

        $physicalCount = UniformPhysicalCount::where('status', 'Posted')
            ->whereDate('begin_date', $data['begin_date'])
            ->whereDate('ending_date', $data['ending_date'])
            ->first();

        if (!$physicalCount) {
            abort(422, 'No posted physical count found for the selected date range.');
        }

        $this->ensureNoPendingVariance();
        $this->ensureNoVarianceForPhysicalCount($physicalCount->id);

        $variance = UniformVariance::create([
            'variance_no' => $this->nextNumber(
                'uniform_variances',
                'variance_no',
                'VR',
                7
            ),
            'physical_count_id' => $physicalCount->id,
            'begin_date' => $physicalCount->begin_date,
            'ending_date' => $physicalCount->ending_date,
            'status' => 'Unposted',
            'remarks' => $data['remarks'] ?? null,
            'prepared_by' => $this->userName(),
        ]);

        $this->replaceVarianceItems(
            $variance,
            $physicalCount,
            $data['items']
        );

        return response()->json(
            $variance->load(
                'physicalCount',
                'items.item.size',
                'items.item.gender'
            ),
            201
        );
    });
}

    public function reviewVariance(Request $request, int $id)
    {
        $data = $request->validate([
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.uniform_item_id' => ['required_with:items', 'integer', 'distinct', 'exists:uniform_items,id'],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'items.*.reason_of_adjustment' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($data, $id) {
            $variance = UniformVariance::with('physicalCount')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($variance->status !== 'Unposted') {
                throw ValidationException::withMessages(['status' => 'Only unposted variances can be reviewed.']);
            }

            if (!empty($data['items'])) {
                $physicalCount = $this->postedPhysicalCount($variance->physical_count_id, true);
                $this->replaceVarianceItems($variance, $physicalCount, $data['items']);
            }

            $variance->update([
                'status' => 'Reviewed',
                'reviewed_by' => $this->userName(),
                'reviewed_at' => now(),
            ]);

            return response()->json($variance->load('physicalCount', 'items.item.size', 'items.item.gender'));
        });
    }

    public function postVariance(int $id)
    {
        return DB::transaction(function () use ($id) {
            $variance = UniformVariance::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($variance->status !== 'Reviewed') {
                throw ValidationException::withMessages(['status' => 'Only reviewed variances can be posted.']);
            }
            UniformPeriodLock::ensureDateIsOpen($variance->ending_date, UniformPeriodLock::TYPE_RECEIVING);

            $variance->update([
                'status' => 'Posted',
                'posted_by' => $this->userName(),
                'posted_at' => now(),
            ]);

            return response()->json($variance->load('physicalCount', 'items.item.size', 'items.item.gender'));
        });
    }

    public function cancelVariance(Request $request, int $id)
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string'],
        ]);

        return DB::transaction(function () use ($data, $id) {
            $variance = UniformVariance::whereKey($id)->lockForUpdate()->firstOrFail();

            if (!in_array($variance->status, ['Unposted', 'Reviewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Only unposted or reviewed variances can be cancelled.']);
            }

            $variance->update([
                'status' => 'Cancelled',
                'cancelled_by' => $this->userName(),
                'cancelled_at' => now(),
                'cancelled_reason' => $data['cancel_reason'],
            ]);

            return response()->json($variance->load('physicalCount', 'items.item.size', 'items.item.gender'));
        });
    }

    private function varianceQuery(Request $request)
    {
        $query = UniformVariance::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('begin_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ending_date', '<=', $request->date_to);
        }

        if ($request->filled('key_word')) {
            $column = $request->input('column_type');
            $keyword = $request->input('key_word');

            if (in_array($column, ['variance_no', 'status', 'prepared_by', 'reviewed_by', 'posted_by', 'cancelled_by'], true)) {
                $query->where($column, 'LIKE', "%{$keyword}%");
            }

            if ($column === 'physical_count.physical_count_no') {
                $query->whereHas('physicalCount', fn ($physicalCount) => $physicalCount
                    ->where('physical_count_no', 'LIKE', "%{$keyword}%"));
            }
        }

        return $query;
    }

    private function replaceVarianceItems(UniformVariance $variance, UniformPhysicalCount $physicalCount, array $items): void
    {
        $lines = $this->varianceLines($physicalCount, $items);

        $variance->items()->delete();
        foreach ($lines as $line) {
            UniformVarianceItem::create([
                'variance_id' => $variance->id,
                ...$line,
            ]);
        }
    }

    private function varianceLines(UniformPhysicalCount $physicalCount, array $inputItems): array
    {
        $overrides = collect($inputItems)->keyBy('uniform_item_id');
        $itemIds = $overrides->keys()->filter()->values()->all();

        $query = $physicalCount->items()->with('item.size', 'item.gender')->orderBy('description');
        if (!empty($itemIds)) {
            $query->whereIn('uniform_item_id', $itemIds);
        }

        return $query->get()->map(function (UniformPhysicalCountItem $item) use ($overrides) {
            $input = $overrides->get($item->uniform_item_id, []);
            $variance = (float) $item->physical_count - (float) $item->should_be_inventory;
            $adjustment = (float) ($input['adjustment'] ?? 0);
            $finalVariance = $variance + $adjustment;
            $standardCost = (float) $item->standard_cost;

            return [
                'uniform_item_id' => $item->uniform_item_id,
                'item_no' => $item->item_no,
                'description' => $item->description,
                'uom' => $item->uom,
                'beginning_inventory' => (float) $item->beginning_inventory,
                'purchasing_department' => (float) $item->purchasing_department,
                'made_to_order' => (float) $item->made_to_order,
                'atd' => (float) $item->atd,
                'free_uniform' => (float) $item->free_uniform,
                'should_be_inventory' => (float) $item->should_be_inventory,
                'physical_count' => (float) $item->physical_count,
                'variance' => $variance,
                'adjustment' => $adjustment,
                'final_variance' => $finalVariance,
                'standard_cost' => $standardCost,
                'total_variance_cost' => $finalVariance * $standardCost,
                'reason_of_adjustment' => $input['reason_of_adjustment'] ?? null,
            ];
        })->values()->all();
    }

    private function postedPhysicalCount(int $id, bool $lock = false): UniformPhysicalCount
    {
        $query = UniformPhysicalCount::with('items')->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }

        $physicalCount = $query->firstOrFail();
        if ($physicalCount->status !== 'Posted') {
            throw ValidationException::withMessages(['physical_count_id' => 'Variance requires a posted physical count.']);
        }

        return $physicalCount;
    }

    private function ensureNoPendingVariance(): void
    {
        if (UniformVariance::whereIn('status', ['Unposted', 'Reviewed'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['status' => 'There is already a pending variance report.']);
        }
    }

    private function ensureNoVarianceForPhysicalCount(int $physicalCountId): void
    {
        if (UniformVariance::where('physical_count_id', $physicalCountId)->where('status', '!=', 'Cancelled')->exists()) {
            throw ValidationException::withMessages(['physical_count_id' => 'A variance report already exists for this physical count.']);
        }
    }

    private function nextNumber(string $table, string $column, string $prefix, int $padding): string
    {
        $latest = DB::table($table)
            ->where($column, 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value($column);

        $next = $latest ? ((int) str_replace("{$prefix}-", '', $latest)) + 1 : 1;

        return $prefix . '-' . str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    private function userName(): ?string
    {
        $user = auth()->user();

        return $user?->name ?? $user?->full_name ?? $user?->employee_name ?? null;
    }
}
