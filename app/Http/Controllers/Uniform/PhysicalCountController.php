<?php

namespace App\Http\Controllers\Uniform;

use App\Exports\Uniform\PhysicalCountDetailedExport;
use App\Exports\Uniform\PhysicalCountExport;
use App\Http\Controllers\Controller;
use App\Models\PhysicalCountCompare;
use App\Models\UniformItem;
use App\Models\UniformPeriodLock;
use App\Models\UniformPhysicalCount;
use App\Models\UniformPhysicalCountItem;
use App\Models\UniformVariance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PhysicalCountController extends Controller
{
    public function physicalCounts(Request $request)
    {
        $query = $this->physicalCountQuery($request)->with('items.item.size', 'items.item.gender')->latest();

        return response()->json($query->paginate((int) $request->input('per_page', 10)));
    }

    public function exportPhysicalCounts(Request $request)
    {
        return Excel::download(
            new PhysicalCountExport($this->physicalCountQuery($request)->latest()->get()),
            'uniform-physical-counts.xls'
        );
    }

    public function exportPhysicalCountDetails(int $id)
    {
        $physicalCount = UniformPhysicalCount::with('items')->findOrFail($id);

        return Excel::download(
            new PhysicalCountDetailedExport($physicalCount),
            'physical-count-detailed-' . $physicalCount->physical_count_no . '.xlsx'
        );
    }

    public function previewPhysicalCount(Request $request)
    {
        $data = $request->validate([
            'begin_date' => ['required', 'date'],
            'ending_date' => ['required', 'date', 'after_or_equal:begin_date'],
            'items' => ['sometimes', 'array'],
            'items.*.uniform_item_id' => ['required_with:items', 'integer', 'exists:uniform_items,id'],
            'items.*.physical_count' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_remarks' => ['nullable', 'string'],
        ]);

        return response()->json([
            'items' => $this->physicalCountLines($data['begin_date'], $data['ending_date'], $data['items'] ?? []),
        ]);
    }

    public function storePhysicalCount(Request $request)
    {
        $data = $request->validate([
            'begin_date' => ['required', 'date'],
            'ending_date' => ['required', 'date', 'after_or_equal:begin_date'],
            'counted_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.uniform_item_id' => ['required', 'integer', 'distinct', 'exists:uniform_items,id'],
            'items.*.physical_count' => ['required', 'numeric', 'min:0'],
            'items.*.item_remarks' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($data) {
            $this->ensureNoPendingPhysicalCount();
            $this->ensureNoPhysicalCountForPeriod($data['begin_date'], $data['ending_date']);

            $physicalCount = UniformPhysicalCount::create([
                'physical_count_no' => $this->nextNumber('uniform_physical_counts', 'physical_count_no', 'PC', 7),
                'begin_date' => $data['begin_date'],
                'ending_date' => $data['ending_date'],
                'counted_by' => $data['counted_by'] ?? null,
                'status' => 'Unposted',
                'remarks' => $data['remarks'] ?? null,
                'prepared_by' => $this->userName(),
            ]);

            $this->replacePhysicalCountItems($physicalCount, $data['items']);

            return response()->json($physicalCount->load('items.item.size', 'items.item.gender'), 201);
        });
    }

    public function reviewPhysicalCount(Request $request, int $id)
    {
        $data = $request->validate([
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.uniform_item_id' => ['required_with:items', 'integer', 'distinct', 'exists:uniform_items,id'],
            'items.*.physical_count' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.item_remarks' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($data, $id) {
            $physicalCount = UniformPhysicalCount::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($physicalCount->status !== 'Unposted') {
                throw ValidationException::withMessages(['status' => 'Only unposted physical counts can be reviewed.']);
            }

            if (!empty($data['items'])) {
                $this->replacePhysicalCountItems($physicalCount, $data['items']);
            }

            $physicalCount->update([
                'status' => 'Reviewed',
                'reviewed_by' => $this->userName(),
                'reviewed_at' => now(),
            ]);

            return response()->json($physicalCount->load('items.item.size', 'items.item.gender'));
        });
    }

    public function postPhysicalCount(int $id)
    {
        return DB::transaction(function () use ($id) {
            $physicalCount = UniformPhysicalCount::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($physicalCount->status !== 'Reviewed') {
                throw ValidationException::withMessages(['status' => 'Only reviewed physical counts can be posted.']);
            }
            UniformPeriodLock::ensureDateIsOpen($physicalCount->ending_date, UniformPeriodLock::TYPE_RECEIVING);

            foreach ($physicalCount->items as $item) {
                PhysicalCountCompare::updateOrCreate(
                    [
                        'uniform_item_id' => $item->uniform_item_id,
                        'beginning_date' => $physicalCount->ending_date->format('Y-m-d'),
                    ],
                    [
                        'physical_count_id' => $physicalCount->id,
                        'item_no' => $item->item_no,
                        'stock_on_hand' => $item->physical_count,
                    ]
                );
            }

            $physicalCount->update([
                'status' => 'Posted',
                'posted_by' => $this->userName(),
                'posted_at' => now(),
            ]);

            return response()->json($physicalCount->load('items.item.size', 'items.item.gender'));
        });
    }

    public function cancelPhysicalCount(Request $request, int $id)
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string'],
        ]);

        return DB::transaction(function () use ($data, $id) {
            $physicalCount = UniformPhysicalCount::whereKey($id)->lockForUpdate()->firstOrFail();

            if (!in_array($physicalCount->status, ['Unposted', 'Reviewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Only unposted or reviewed physical counts can be cancelled.']);
            }

            if (UniformVariance::where('physical_count_id', $physicalCount->id)->where('status', 'Posted')->exists()) {
                throw ValidationException::withMessages(['status' => 'Physical count cannot be cancelled because a posted variance already uses it.']);
            }

            UniformVariance::where('physical_count_id', $physicalCount->id)
                ->whereIn('status', ['Unposted', 'Reviewed'])
                ->update([
                    'status' => 'Cancelled',
                    'cancelled_by' => $this->userName(),
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Cancelled together with physical count.',
                ]);

            $physicalCount->update([
                'status' => 'Cancelled',
                'cancelled_by' => $this->userName(),
                'cancelled_at' => now(),
                'cancelled_reason' => $data['cancel_reason'],
            ]);

            return response()->json($physicalCount->load('items.item.size', 'items.item.gender'));
        });
    }

    private function replacePhysicalCountItems(UniformPhysicalCount $physicalCount, array $items): void
    {
        $lines = $this->physicalCountLines(
            $physicalCount->begin_date->format('Y-m-d'),
            $physicalCount->ending_date->format('Y-m-d'),
            $items
        );

        $physicalCount->items()->delete();
        foreach ($lines as $line) {
            UniformPhysicalCountItem::create([
                'physical_count_id' => $physicalCount->id,
                ...$line,
            ]);
        }
    }

    private function physicalCountQuery(Request $request)
    {
        $query = UniformPhysicalCount::query();

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

            if (in_array($column, ['physical_count_no', 'counted_by', 'status', 'prepared_by', 'reviewed_by', 'posted_by', 'cancelled_by'], true)) {
                $query->where($column, 'LIKE', "%{$keyword}%");
            }
        }

        return $query;
    }

    private function physicalCountLines(string $beginDate, string $endingDate, array $inputItems): array
    {
        $overrides = collect($inputItems)->keyBy('uniform_item_id');
        $itemIds = $overrides->keys()->filter()->values()->all();

        $items = UniformItem::query()
            ->with('size', 'gender')
            ->when(!empty($itemIds), fn ($query) => $query->whereIn('id', $itemIds))
            ->where('status', 'active')
            ->orderBy('description')
            ->get();

        $movement = $this->movementTotals($beginDate, $endingDate);

        return $items->map(function (UniformItem $item) use ($beginDate, $movement, $overrides) {
            $input = $overrides->get($item->id, []);
            $beginning = $this->beginningInventory($item, $beginDate);
            $purchasing = (float) ($movement['purchasing'][$item->id] ?? 0);
            $madeToOrder = (float) ($movement['made_to_order'][$item->id] ?? 0);
            $atd = (float) ($movement['atd'][$item->item_no] ?? 0);
            $freeUniform = (float) ($movement['free_uniform'][$item->item_no] ?? 0);
            $totalPurchases = $purchasing + $madeToOrder;
            $totalOutgoing = $atd + $freeUniform;
            $shouldBe = $beginning + $totalPurchases - $totalOutgoing;

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
                'total_purchases' => $totalPurchases,
                'total_outgoing' => $totalOutgoing,
                'should_be_inventory' => $shouldBe,
                'physical_count' => (float) ($input['physical_count'] ?? 0),
                'standard_cost' => (float) $item->standard_unit_price,
                'item_remarks' => $input['item_remarks'] ?? null,
            ];
        })->values()->all();
    }

    private function movementTotals(string $beginDate, string $endingDate): array
    {
        $receivings = DB::table('uniform_receiving_items as uri')
            ->join('uniform_receivings as ur', 'ur.id', '=', 'uri.receiving_id')
            ->join('uniform_replenishments as rpl', 'rpl.id', '=', 'ur.replenishment_id')
            ->where('ur.status', 'Posted')
            ->whereDate('ur.posted_at', '>=', $beginDate)
            ->whereDate('ur.posted_at', '<=', $endingDate)
            ->select('uri.uniform_item_id', 'rpl.replenishment_type', DB::raw('SUM(uri.quantity) as quantity'))
            ->groupBy('uri.uniform_item_id', 'rpl.replenishment_type')
            ->get();

        $issuances = DB::table('request_and_issuance_items as rii')
            ->join('request_and_issuances as rai', 'rai.request_number', '=', 'rii.requested_number_id')
            ->where('rai.status', 'Posted')
            ->whereDate('rai.date_posted', '>=', $beginDate)
            ->whereDate('rai.date_posted', '<=', $endingDate)
            ->select('rii.uniform_item_no', 'rai.issuance_type', DB::raw('SUM(rii.requested_quantity) as quantity'))
            ->groupBy('rii.uniform_item_no', 'rai.issuance_type')
            ->get();

        $totals = [
            'purchasing' => [],
            'made_to_order' => [],
            'atd' => [],
            'free_uniform' => [],
        ];

        foreach ($receivings as $row) {
            $type = strtolower((string) $row->replenishment_type);
            $bucket = str_contains($type, 'made') ? 'made_to_order' : 'purchasing';
            $totals[$bucket][$row->uniform_item_id] = (float) $row->quantity;
        }

        foreach ($issuances as $row) {
            $type = strtolower((string) $row->issuance_type);
            $bucket = str_contains($type, 'free') ? 'free_uniform' : 'atd';
            $totals[$bucket][$row->uniform_item_no] = (float) $row->quantity;
        }

        return $totals;
    }

    private function beginningInventory(UniformItem $item, string $beginDate): float
    {
        $compare = PhysicalCountCompare::where('uniform_item_id', $item->id)
            ->whereDate('beginning_date', '<=', $beginDate)
            ->latest('beginning_date')
            ->first();

        return $compare ? (float) $compare->stock_on_hand : (float) $item->stock_on_hand;
    }

    private function ensureNoPendingPhysicalCount(): void
    {
        if (UniformPhysicalCount::whereIn('status', ['Unposted', 'Reviewed'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['status' => 'There is already a pending physical count.']);
        }
    }

    private function ensureNoPhysicalCountForPeriod(string $beginDate, string $endingDate): void
    {
        if (UniformPhysicalCount::where('begin_date', $beginDate)->where('ending_date', $endingDate)->where('status', '!=', 'Cancelled')->exists()) {
            throw ValidationException::withMessages(['begin_date' => 'A physical count already exists for this period.']);
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
