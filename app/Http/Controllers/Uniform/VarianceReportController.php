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
use App\Models\RequestAndIssuanceItems;
use App\Models\UniformReceivingItem;
use Log;
use Carbon\Carbon;


class VarianceReportController extends Controller
{
    public function __construct(private UniformMovementService $movementService)
    {
    }

    public function variances(Request $request)
    {
        $query = $this->varianceQuery($request)->with('physicalCount', 'items.item.size', 'items.item.gender')->orderByDesc('variance_no');

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
            'items.*.uniform_item_id' => [
                'required_with:items',
                'integer',
                'exists:uniform_items,id'
            ],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'items.*.reason_of_adjustment' => ['nullable', 'string'],
        ]);

        $physicalCounts = $this->findPhysicalCounts(
            $data['begin_date'],
            $data['ending_date']
        );

        return response()->json([
            'physical_counts' => $physicalCounts,
            'items' => $this->varianceLines(
                $data['begin_date'],
                $data['ending_date'],
                $physicalCounts,
                $data['items'] ?? []
            ),
        ]);
    }

    public function getVarianceItemDetails(Request $request)
    {
        try {
            $item = UniformItem::find($request->uniform_item_id);

            if (!$item) {
                return $this->sendError([
                    'message' => 'Uniform item not found'
                ], 404);
            }

            $dateFrom = Carbon::parse($request->begin_date)->endOfDay();
            $dateTo = Carbon::parse($request->end_date)->endOfDay();

            $requestDetails = RequestAndIssuanceItems::with('issuance')
                ->where('uniform_item_no', $item->item_no)
                ->whereHas('issuance', function ($query) use ($dateFrom, $dateTo) {
                    $query->where('status', 'Posted')
                        ->whereBetween('date_posted', [$dateFrom, $dateTo]);
                })
                ->get()
                ->map(function ($item) {
                    return [
                        'request_number' => $item->issuance?->request_number,
                        'date_requested' => $item->issuance?->date_requested,
                        'date_posted' => $item->issuance?->date_posted,
                        'employee_name' => $item->issuance?->employee_name,
                        'issuance_type' => $item->issuance?->issuance_type,
                        'branch' => $item->issuance?->branch,
                        'quantity' => (float) $item->requested_quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_amount' => (float) $item->total_amount,
                        'uom' => $item->uom,
                        'remarks' => $item->item_remarks,
                        'position' => $item->issuance?->position,
                    ];
                });

            $receivingDetails = UniformReceivingItem::with([
                'receiving.supplier',
                'receiving.replenishment',
                'item'
            ])
                ->whereHas('item', function ($query) use ($item) {
                    $query->where('item_no', $item->item_no);
                })
                ->whereHas('receiving', function ($query) use ($dateFrom, $dateTo) {
                    $query->where('status', 'Posted')
                        ->whereBetween('date_received', [$dateFrom, $dateTo]);
                })
                ->get()
                ->map(function ($item) {
                    return [
                        'receiving_no' => $item->receiving?->receiving_no,
                        'date_received' => $item->receiving?->date_received,
                        'standard_uom' => $item->item?->standard_unit,
                        'total_purchases' => (float) $item->quantity,
                        'replenishment_type' => $item->receiving?->replenishment?->replenishment_type,
                        'unit_price' => (float) $item->unit_price,
                        'total_net_amount' => (float) $item->total_amount,
                        'supplier' => $item->receiving?->supplier?->supplier_name,
                        'crf_no' => $item->receiving?->crf_no,
                        'document_no' => $item->receiving?->document_no,
                        'remarks' => $item->remarks,
                    ];
                });

            $item->setAttribute('request_details', $requestDetails);
            $item->setAttribute('receiving_details', $receivingDetails);

            return $this->sendResponse($item);

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getVarianceItemById($id)
    {
        try {

            $varianceItem = UniformVarianceItem::with('varianceHeader', 'item')
                ->find($id);

            if (!$varianceItem) {
                return $this->sendError([
                    'message' => 'Variance Item not found'
                ], 404);
            }

            $dateFrom = $varianceItem->varianceHeader?->begin_date;
            $dateTo   = $varianceItem->varianceHeader?->ending_date;

            $requestDetails = collect();
            $receivingDetails = collect();

            if ($varianceItem->item && $dateFrom && $dateTo) {

                $dateFrom = Carbon::parse($dateFrom)->startOfDay();
                $dateTo   = Carbon::parse($dateTo)->endOfDay();
                $requestDetails = RequestAndIssuanceItems::with('issuance')
                    ->where('uniform_item_no', $varianceItem->item_no)
                    ->whereHas('issuance', function ($query) use ($dateFrom, $dateTo) {
                        $query->where('status', 'Posted')
                            ->whereBetween('date_posted', [$dateFrom, $dateTo]);
                    })
                    ->get()
                    ->map(function ($item) {
                        return [
                            'request_number' => $item->issuance?->request_number,
                            'date_requested' => $item->issuance?->date_requested,
                            'date_posted'    => $item->issuance?->date_posted,
                            'employee_name'  => $item->issuance?->employee_name,
                            'issuance_type'  => $item->issuance?->issuance_type,
                            'branch'         => $item->issuance?->branch,
                            'quantity'       => (float) $item->requested_quantity,
                            'unit_price'     => (float) $item->unit_price,
                            'total_amount'   => (float) $item->total_amount,
                            'uom'            => $item->uom,
                            'remarks'        => $item->item_remarks,
                            'position'       => $item->issuance?->position,
                        ];
                    });
                $receivingDetails = UniformReceivingItem::with([
                    'receiving.supplier',
                    'receiving.replenishment',
                    'item'
                ])
                ->whereHas('item', function ($query) use ($varianceItem) {
                    $query->where('item_no', $varianceItem->item_no);
                })
                ->whereHas('receiving', function ($query) use ($dateFrom, $dateTo) {
                    $query->where('status', 'Posted')
                        ->whereBetween('date_received', [$dateFrom, $dateTo]);
                })
                ->get()
                ->map(function ($item) {
                    return [
                        'receiving_no'       => $item->receiving?->receiving_no,
                        'date_received'      => $item->receiving?->date_received,
                        'standard_uom'       => $item->item?->standard_unit,
                        'total_purchases'    => (float) $item->quantity,
                        'replenishment_type' => $item->receiving?->replenishment?->replenishment_type,
                        'unit_price'         => (float) $item->unit_price,
                        'total_net_amount'   => (float) $item->total_amount,
                        'supplier'           => $item->receiving?->supplier?->supplier_name,
                        'crf_no'             => $item->receiving?->crf_no,
                        'document_no'        => $item->receiving?->document_no,
                        'remarks'            => $item->remarks,
                    ];
                });
            }

            $varianceItem->setAttribute('request_details', $requestDetails);
            $varianceItem->setAttribute('receiving_details', $receivingDetails);

            return $this->sendResponse($varianceItem);

        } catch (\Exception $e) {

            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function storeVariance(Request $request)
    {
        $data = $request->validate([
            'begin_date' => ['required', 'date'],
            'ending_date' => ['required', 'date', 'after:begin_date'],
            'remarks' => ['string','nullable'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.uniform_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:uniform_items,id',
            ],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'items.*.reason_of_adjustment' => [
                'nullable',
                'string',
            ],
        ]);

        return DB::transaction(function () use ($data) {
            $this->ensureNoPendingVariance();
            $this->ensureNoPostedVarianceForDateRange(
                $data['begin_date'],
                $data['ending_date']
            );
            $physicalCount = $this->findPhysicalCount(
                $data['begin_date'],
                $data['ending_date']
            );
            if (!$physicalCount) {
                throw ValidationException::withMessages([
                    'begin_date' => 'No posted physical count found for the selected date range.',
                ]);
            }
            $variance = UniformVariance::create([
                'physical_count_id' => $physicalCount->id,
                'begin_date' => $data['begin_date'],
                'ending_date' => $data['ending_date'],
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
                ->wherein('status', ['Posted','Updated'])
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
        $query = UniformVariance::query()
            ->orderBy('variance_no','desc');

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

    private function varianceLines(
        string $beginDate,
        string $endingDate,
        $physicalCounts,
        array $inputItems
    ): array {
 
        if ($physicalCounts instanceof UniformPhysicalCount) {
            $physicalCounts = collect([$physicalCounts]);
        } elseif (!$physicalCounts instanceof \Illuminate\Support\Collection) {
            $physicalCounts = collect($physicalCounts ?? []);
        }

        $overrides = collect($inputItems)
            ->keyBy('uniform_item_id');

        $itemIds = $overrides
            ->keys()
            ->filter()
            ->values()
            ->all();

        $physicalCountItems = collect();

        foreach ($physicalCounts as $physicalCount) {

            foreach ($physicalCount->items as $item) {

                $physicalCountItems->put(
                    $item->uniform_item_id,
                    $item
                );
            }
        }

        $items = UniformItem::query()
            ->with('size', 'gender')
            ->when(
                !empty($itemIds),
                fn ($query) => $query->whereIn('id', $itemIds)
            )
            ->where('status', 'active')
            ->orderBy('item_no')
            ->get();

        $movement = $this->movementService->movementTotals(
            $beginDate,
            $endingDate
        );

        $beginningByItem = $this->movementService->beginningInventoryBatch(
            $items,
            $beginDate
        );

        return $items->map(function (UniformItem $item) use (
            $movement,
            $beginningByItem,
            $physicalCountItems,
            $overrides
        ) {

            $input = $overrides->get($item->id, []);
            $pcItem = $physicalCountItems->get($item->id);
            $beginning = $beginningByItem[$item->id] ?? 0;
            $purchasing = (float) ($movement['purchasing'][$item->id] ?? 0);
            $madeToOrder = (float) ($movement['made_to_order'][$item->id] ?? 0);
            $atd = (float) ($movement['atd'][$item->item_no] ?? 0);
            $freeUniform = (float) ($movement['free_uniform'][$item->item_no] ?? 0);
            $shouldBe = ($beginning + $purchasing + $madeToOrder) - ($atd + $freeUniform);
            $physicalCountQty = $pcItem? (float) $pcItem->physical_count: 0.0;
            $standardCost = $pcItem? (float) $pcItem->standard_cost: (float) $item->standard_unit_price;
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
                'total_variance_cost' =>$finalVariance * $standardCost,
                'reason_of_adjustment' =>$input['reason_of_adjustment'] ?? null,
            ];

        })->values()->all();
    }
    private function findPhysicalCounts(string $beginDate, string $endingDate)
    {
        return UniformPhysicalCount::with('items')
            ->whereIn('status', ['Posted', 'Updated'])
            ->whereDate('begin_date', $beginDate)
            ->whereDate('ending_date', $endingDate)
            ->orderBy('begin_date')
            ->get();
    }
    private function findPhysicalCount(string $beginDate, string $endingDate): ?UniformPhysicalCount
    {
        return $this->findPhysicalCounts($beginDate, $endingDate)->first();
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
