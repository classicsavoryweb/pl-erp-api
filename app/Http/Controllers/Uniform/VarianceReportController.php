<?php

namespace App\Http\Controllers\Uniform;

use App\Exports\Uniform\VarianceReportDetailedExport;
use App\Exports\Uniform\VarianceReportExport;
use App\Http\Controllers\Controller;
use App\Http\Services\UniformMovementService;
use App\Models\UniformItem;
use App\Models\UniformPeriodLock;
use App\Models\UniformPhysicalCount;
use App\Models\UniformVariance;
use App\Models\UniformVarianceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class VarianceReportController extends Controller
{
    public function __construct(private UniformMovementService $movementService)
    {
    }

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
            'ending_date' => ['required', 'date', 'after:begin_date'],
            'items' => ['sometimes', 'array'],
            'items.*.uniform_item_id' => ['required_with:items', 'integer', 'exists:uniform_items,id'],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'items.*.reason_of_adjustment' => ['nullable', 'string'],
        ]);

        $physicalCount = $this->findPhysicalCount($data['begin_date'], $data['ending_date']);

        return response()->json([
            'physical_count' => $physicalCount,
            'items' => $this->varianceLines($data['begin_date'], $data['ending_date'], $physicalCount, $data['items'] ?? []),
        ]);
    }

    public function storeVariance(Request $request)
    {
        $data = $request->validate([
            'begin_date' => ['required', 'date'],
            'ending_date' => ['required', 'date', 'after:begin_date'],
            'remarks' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.uniform_item_id' => ['required', 'integer', 'distinct', 'exists:uniform_items,id'],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'items.*.reason_of_adjustment' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($data) {
            $this->ensureNoPendingVariance();
            $this->ensureNoPostedVarianceForDateRange($data['begin_date'], $data['ending_date']);

            $physicalCount = $this->findPhysicalCount($data['begin_date'], $data['ending_date']);

            $variance = UniformVariance::create([
                'variance_no' => $this->nextNumber('uniform_variances', 'variance_no', 'VR', 7),
                'physical_count_id' => $physicalCount?->id,
                'begin_date' => $data['begin_date'],
                'ending_date' => $data['ending_date'],
                'status' => 'Unposted',
                'remarks' => $data['remarks'],
                'prepared_by' => $this->userName(),
            ]);

            $this->replaceVarianceItems($variance, $physicalCount, $data['items']);

            return response()->json($variance->load('physicalCount', 'items.item.size', 'items.item.gender'), 201);
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
                $physicalCount = $this->findPhysicalCount($variance->begin_date->format('Y-m-d'), $variance->ending_date->format('Y-m-d'));
                $variance->physical_count_id = $physicalCount?->id;
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

            $postedPhysicalCount = UniformPhysicalCount::where('begin_date', $variance->begin_date)
                ->where('ending_date', $variance->ending_date)
                ->where('status', 'Posted')
                ->first();

            if (!$postedPhysicalCount) {
                throw ValidationException::withMessages(['status' => 'Selected date range is not posted in physical count.']);
            }

            $variance->physical_count_id = $postedPhysicalCount->id;

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

    private function replaceVarianceItems(UniformVariance $variance, ?UniformPhysicalCount $physicalCount, array $items): void
    {
        $lines = $this->varianceLines($variance->begin_date->format('Y-m-d'), $variance->ending_date->format('Y-m-d'), $physicalCount, $items);

        $variance->items()->delete();
        foreach ($lines as $line) {
            UniformVarianceItem::create([
                'variance_id' => $variance->id,
                ...$line,
            ]);
        }
    }

    /**
     * Independently recomputes beginning/purchases/outgoing/should-be for every active
     * item (same as Physical Count generation), then left-joins the physical count qty
     * + standard cost for the matching (by date) physical count if one exists, matching
     * the legacy system's behaviour of not requiring a physical count to exist yet.
     */
    private function varianceLines(string $beginDate, string $endingDate, ?UniformPhysicalCount $physicalCount, array $inputItems): array
    {
        $overrides = collect($inputItems)->keyBy('uniform_item_id');
        $itemIds = $overrides->keys()->filter()->values()->all();
        $physicalCountItems = $physicalCount ? $physicalCount->items->keyBy('uniform_item_id') : collect();

        $items = UniformItem::query()
            ->with('size', 'gender')
            ->when(!empty($itemIds), fn ($query) => $query->whereIn('id', $itemIds))
            ->where('status', 'active')
            ->orderBy('description')
            ->get();

        $movement = $this->movementService->movementTotals($beginDate, $endingDate);
        $beginningByItem = $this->movementService->beginningInventoryBatch($items, $beginDate);

        return $items->map(function (UniformItem $item) use ($movement, $beginningByItem, $physicalCountItems, $overrides) {
            $input = $overrides->get($item->id, []);
            $pcItem = $physicalCountItems->get($item->id);

            $beginning = $beginningByItem[$item->id];
            $purchasing = (float) ($movement['purchasing'][$item->id] ?? 0);
            $madeToOrder = (float) ($movement['made_to_order'][$item->id] ?? 0);
            $atd = (float) ($movement['atd'][$item->item_no] ?? 0);
            $freeUniform = (float) ($movement['free_uniform'][$item->item_no] ?? 0);

            $shouldBe = ($beginning + $purchasing + $madeToOrder) - ($atd + $freeUniform);
            $physicalCountQty = $pcItem ? (float) $pcItem->physical_count : 0.0;
            $standardCost = $pcItem ? (float) $pcItem->standard_cost : (float) $item->standard_unit_price;

            $variance = $physicalCountQty - $shouldBe;
            $adjustment = (float) ($input['adjustment'] ?? 0);
            $finalVariance = $variance + $adjustment;

            return [
                'uniform_item_id' => $item->id,
                'item_no' => $item->item_no,
                'description' => $item->description,
                'uom' => $item->standard_unit,
                'beginning_inventory' => $beginning,
                'purchasing_department' => $purchasing,
                'made_to_order' => $madeToOrder,
                'atd' => $atd,
                'free_uniform' => $freeUniform,
                'should_be_inventory' => $shouldBe,
                'physical_count' => $physicalCountQty,
                'variance' => $variance,
                'adjustment' => $adjustment,
                'final_variance' => $finalVariance,
                'standard_cost' => $standardCost,
                'total_variance_cost' => $finalVariance * $standardCost,
                'reason_of_adjustment' => $input['reason_of_adjustment'] ?? null,
            ];
        })->values()->all();
    }

    /**
     * Finds the physical count covering the exact same date range, matching the legacy
     * system's date-equality linkage (no foreign key on that side). Any non-cancelled
     * status qualifies here; postVariance() separately requires it to be Posted.
     */
    private function findPhysicalCount(string $beginDate, string $endingDate): ?UniformPhysicalCount
    {
        return UniformPhysicalCount::with('items')
            ->where('begin_date', $beginDate)
            ->where('ending_date', $endingDate)
            ->where('status', '!=', 'Cancelled')
            ->latest('id')
            ->first();
    }

    private function ensureNoPendingVariance(): void
    {
        if (UniformVariance::whereIn('status', ['Unposted', 'Reviewed'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['status' => 'Variance has pending transaction']);
        }
    }

    private function ensureNoPostedVarianceForDateRange(string $beginDate, string $endingDate): void
    {
        if (UniformVariance::where('begin_date', $beginDate)->where('ending_date', $endingDate)->where('status', 'Posted')->exists()) {
            throw ValidationException::withMessages(['begin_date' => 'Date Range is Already Selected.']);
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
