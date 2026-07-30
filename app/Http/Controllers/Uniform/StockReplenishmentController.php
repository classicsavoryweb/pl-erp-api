<?php

namespace App\Http\Controllers\Uniform;

use App\Http\Controllers\Controller;
use App\Exports\Uniform\ReceivingDetailedExport;
use App\Exports\Uniform\ReplenishmentDetailedExport;
use App\Models\Supplier;
use App\Models\UniformItem;
use App\Models\UniformItemSupplier;
use App\Models\UniformPeriodLock;
use App\Models\UniformReceiving;
use App\Models\UniformReplenishment;
use App\Models\UniformReplenishmentItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StockReplenishmentController extends Controller
{
    public function options()
    {
        try {
            return $this->sendResponse([
                'suppliers' => Supplier::orderBy('vendor_name')->get(),
                'items' => UniformItem::with(['size', 'gender', 'suppliers'])
                    ->where('status', 'active')
                    ->orderBy('description')
                    ->get(),
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function replenishments(Request $request)
    {
        try {
            $records = UniformReplenishment::with([
                'supplier', 'items.item.size', 'items.item.gender',
            ])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
                ->when($request->filled('key_word'), function ($query) use ($request) {
                    $keyword = $request->key_word;
                    $query->where(function ($nested) use ($keyword) {
                        $nested->where('request_no', 'LIKE', "%{$keyword}%")
                            ->orWhereHas('supplier', fn ($supplier) => $supplier
                                ->where('vendor_name', 'LIKE', "%{$keyword}%"));
                    });
                })
                ->latest('id')
                ->paginate($request->integer('per_page', 10));

            return $this->sendResponse($records);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function exportReplenishmentDetails(int $id)
    {
        $record = UniformReplenishment::with(['supplier', 'items.item'])
            ->findOrFail($id);

        return Excel::download(
            new ReplenishmentDetailedExport($record),
            'uniform-replenishment-detailed-' . $record->request_no . '.xlsx'
        );
    }

    public function generateReplenishmentList(Request $request)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            ]);

            $supplier = Supplier::findOrFail($validated['supplier_id']);
            $items = UniformItem::with(['size', 'gender', 'suppliers'])
                ->where('status', 'active')
                ->whereHas('suppliers', fn ($query) => $query->where('vendor_no', $supplier->vendor_number))
                ->orderBy('description')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'No active uniform items are linked to the selected supplier.',
                ]);
            }

            return $this->sendResponse([
                'message' => 'Replenishment list generated successfully',
                'items' => $this->formatReplenishmentLines($supplier, $items),
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function importReplenishmentItems(Request $request)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
                'supplier_item_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            ]);

            $supplier = Supplier::findOrFail($validated['supplier_id']);
            $rows = $this->sheetRows($request->file('supplier_item_file')->getRealPath());
            if (count($rows) < 2) {
                throw ValidationException::withMessages([
                    'supplier_item_file' => 'The imported supplier/item list has no item rows.',
                ]);
            }

            $headers = array_map(fn ($value) => $this->normalizeHeader($value), $rows[0]);
            $itemNoIndex = array_search('itemno', $headers, true);
            if ($itemNoIndex === false) {
                throw ValidationException::withMessages([
                    'supplier_item_file' => 'The imported file must include an Item No column.',
                ]);
            }

            $requestedIndex = $this->firstHeaderIndex($headers, ['requestedqty', 'requestedquantity', 'quantity', 'qty']);
            $approvedIndex = $this->firstHeaderIndex($headers, ['approvedqty', 'approvedquantity']);
            $unitPriceIndex = $this->firstHeaderIndex($headers, ['unitprice', 'price']);
            $remarksIndex = $this->firstHeaderIndex($headers, ['remarks', 'itemremarks']);

            $itemNos = collect(array_slice($rows, 1))
                ->map(fn ($row) => trim((string) ($row[$itemNoIndex] ?? '')))
                ->filter()
                ->unique()
                ->values();

            $items = UniformItem::with(['size', 'gender', 'suppliers'])
                ->whereIn('item_no', $itemNos)
                ->where('status', 'active')
                ->get()
                ->keyBy('item_no');

            $lines = collect(array_slice($rows, 1))
                ->map(function ($row) use ($itemNoIndex, $requestedIndex, $approvedIndex, $unitPriceIndex, $remarksIndex, $items, $supplier) {
                    $itemNo = trim((string) ($row[$itemNoIndex] ?? ''));
                    $item = $items->get($itemNo);
                    if (!$item) {
                        return null;
                    }

                    $supplierPrice = $this->supplierPrice($item, $supplier);
                    $requestedQty = $this->numericCell($row, $requestedIndex, $item->minimum_rpl ?? 1);
                    $approvedQty = $this->numericCell($row, $approvedIndex, $requestedQty);
                    $unitPrice = $this->numericCell($row, $unitPriceIndex, $supplierPrice ?? $item->standard_unit_price ?? 0);

                    return [
                        'uniform_item_id' => $item->id,
                        'item' => $item,
                        'requested_qty' => $requestedQty,
                        'approved_qty' => $approvedQty,
                        'unit_price' => $unitPrice,
                        'remarks' => $remarksIndex !== null ? trim((string) ($row[$remarksIndex] ?? '')) : '',
                    ];
                })
                ->filter()
                ->unique('uniform_item_id')
                ->values();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'supplier_item_file' => 'No matching active uniform items were found in the imported file.',
                ]);
            }

            return $this->sendResponse([
                'message' => 'Supplier/item list imported successfully',
                'items' => $lines,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function storeReplenishment(Request $request)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
                'replenishment_type' => ['required', Rule::in(['Purchasing Department', 'Made to Order'])],
                'requested_date' => ['required', 'date'],
                'remarks' => ['nullable', 'string', 'max:2000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.uniform_item_id' => ['required', 'integer', 'distinct', 'exists:uniform_items,id'],
                'items.*.requested_qty' => ['required', 'numeric', 'gt:0'],
                'items.*.approved_qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
                'items.*.remarks' => ['nullable', 'string', 'max:1000'],
            ]);

            $record = DB::transaction(function () use ($request, $validated) {
                $record = UniformReplenishment::create([
                    'supplier_id' => $validated['supplier_id'],
                    'replenishment_type' => $validated['replenishment_type'],
                    'requested_date' => $validated['requested_date'],
                    'status' => 'Unposted',
                    'total_amount' => collect($validated['items'])->sum(
                        fn ($item) => $item['approved_qty'] * $item['unit_price']
                    ),
                    'remarks' => $validated['remarks'] ?? null,
                    'prepared_by' => $this->userName($request),
                ]);

                $record->items()->createMany($validated['items']);

                return $record;
            });

            return $this->sendResponse([
                'message' => 'Replenishment request created successfully',
                'record' => $record->load(['supplier', 'items.item']),
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function reviewReplenishment(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
                'replenishment_type' => ['required', Rule::in(['Purchasing Department', 'Made to Order'])],
                'requested_date' => ['required', 'date'],
                'remarks' => ['nullable', 'string', 'max:2000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.uniform_item_id' => ['required', 'integer', 'distinct', 'exists:uniform_items,id'],
                'items.*.requested_qty' => ['required', 'numeric', 'gt:0'],
                'items.*.approved_qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
                'items.*.remarks' => ['nullable', 'string', 'max:1000'],
            ]);

            $record = DB::transaction(function () use ($request, $validated, $id) {

                $record = UniformReplenishment::where('status', 'Unposted')
                    ->findOrFail($id);

                $record->update([
                    'supplier_id' => $validated['supplier_id'],
                    'replenishment_type' => $validated['replenishment_type'],
                    'requested_date' => $validated['requested_date'],
                    'status' => 'Reviewed',
                    'total_amount' => collect($validated['items'])->sum(
                        fn ($item) => $item['approved_qty'] * $item['unit_price']
                    ),
                    'remarks' => $validated['remarks'] ?? null,
                    'reviewed_by' => $this->userName($request),
                    'reviewed_at' => now(),
                ]);

                // Replace existing items
                $record->items()->delete();

                $record->items()->createMany($validated['items']);

                return $record;
            });

            return $this->sendResponse([
                'message' => 'Replenishment request reviewed successfully',
                'record' => $record->load(['supplier', 'items.item']),
            ]);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }
    public function postReplenishment(Request $request, int $id)
    {
        return $this->transitionReplenishment($request, $id, 'Reviewed', 'Posted');
    }

    public function cancelReplenishment(Request $request, int $id)
    {
        try {
            $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

            DB::transaction(function () use ($request, $validated, $id) {
                $record = UniformReplenishment::lockForUpdate()->findOrFail($id);

                if (!in_array($record->status, ['Unposted', 'Reviewed'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only unposted or reviewed requests can be cancelled.',
                    ]);
                }

                if ($record->items()->where('received_qty', '>', 0)->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'A request with received stock cannot be cancelled.',
                    ]);
                }

                $record->update([
                    'status' => 'Cancelled',
                    'cancelled_by' => $this->userName($request),
                    'cancelled_at' => now(),
                    'cancelled_reason' => $validated['reason'],
                ]);
            });

            return $this->sendResponse(['message' => 'Replenishment request cancelled']);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function replaceReplenishmentSupplier(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            ]);

            $record = DB::transaction(function () use ($validated, $id) {
                $record = UniformReplenishment::with(['items.item.suppliers'])
                    ->lockForUpdate()
                    ->findOrFail($id);

                if (!in_array($record->status, ['Unposted', 'Reviewed'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only unposted or reviewed requests can replace supplier.',
                    ]);
                }

                if ($record->items()->where('received_qty', '>', 0)->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'A request with received stock cannot replace supplier.',
                    ]);
                }

                $supplier = Supplier::findOrFail($validated['supplier_id']);
                $totalAmount = 0;

                foreach ($record->items as $line) {
                    $unitPrice = $this->supplierPrice($line->item, $supplier);
                    if ($unitPrice === null) {
                        throw ValidationException::withMessages([
                            'supplier_id' => "{$line->item->item_no} is not linked to the selected supplier.",
                        ]);
                    }

                    $line->update(['unit_price' => $unitPrice]);
                    $totalAmount += (float) $line->approved_qty * (float) $unitPrice;
                }

                $record->update([
                    'supplier_id' => $supplier->id,
                    'total_amount' => $totalAmount,
                ]);

                return $record->fresh(['supplier', 'items.item.size', 'items.item.gender']);
            });

            return $this->sendResponse([
                'message' => 'Replenishment supplier replaced successfully',
                'record' => $record,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function closeReplenishment(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'reason' => ['required', 'string', 'max:2000']
            ]);

            DB::transaction(function () use ($request, $validated, $id) {

                $record = UniformReplenishment::lockForUpdate()->findOrFail($id);

                if (!in_array($record->status, ['Posted', 'Partially Received'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only posted or partially received requests can be closed.',
                    ]);
                }
                $record->update([
                    'status' => 'Closed',
                    'closed_by' => $this->userName($request),
                    'closed_at' => now(),
                    'closed_reason' => $validated['reason'],
                ]);

                UniformReceiving::where('replenishment_id', $record->id)
                    ->where('status', '!=', 'Posted')
                    ->update([
                        'status' => 'Closed',
                        'closed_by' => $this->userName($request),
                        'closed_at' => now(),
                    ]);
            });
            return $this->sendResponse([
                'message' => 'Replenishment request closed'
            ]);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function receivableReplenishments()
    {
        try {
            $records = UniformReplenishment::with([
                'supplier',
                'items' => function ($query) {
                $query->withSum([
        'receivingItems as pending_received_qty' => function ($q) {
            $q->whereHas('receiving', function ($r) {
                $r->whereIn('status', ['Unposted', 'Reviewed']);
            });
        }
    ], 'quantity');
                },
                'items.item.size',
                'items.item.gender',
            ])
            ->whereIn('status', ['Posted', 'Partially Received'])
            ->latest('id')
            ->get();

            $records->each(function ($record) {

                $record->items = $record->items
                    ->map(function ($item) {

                        $pending = (float) ($item->pending_received_qty ?? 0);

                        $item->remaining_qty =
                            max(
                                0,
                                (float)$item->approved_qty -
                                (float)$item->received_qty -
                                $pending
                            );

                        return $item;
                    })
                    ->filter(function ($item) {
                        return $item->remaining_qty > 0;
                    })
                    ->values();

            });

            // remove replenishments with no remaining items
            $records = $records->filter(function ($record) {
                return $record->items->count() > 0;
            })->values();


            return $this->sendResponse($records);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function receivings(Request $request)
    {
        try {
            $records = UniformReceiving::with([
                'supplier', 'replenishment', 'items.item.size', 'items.item.gender',
            ])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
                ->when($request->filled('key_word'), function ($query) use ($request) {
                    $keyword = $request->key_word;
                    $query->where(function ($nested) use ($keyword) {
                        $nested->where('receiving_no', 'LIKE', "%{$keyword}%")
                            ->orWhere('crf_no', 'LIKE', "%{$keyword}%")
                            ->orWhere('document_no', 'LIKE', "%{$keyword}%")
                            ->orWhereHas('replenishment', fn ($replenishment) => $replenishment
                                ->where('request_no', 'LIKE', "%{$keyword}%"));
                    });
                })
                ->latest('id')
                ->paginate($request->integer('per_page', 10));

            return $this->sendResponse($records);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function exportReceivingDetails(int $id)
    {
        $record = UniformReceiving::with(['supplier', 'replenishment', 'items.item'])
            ->findOrFail($id);

        return Excel::download(
            new ReceivingDetailedExport($record),
            'uniform-receiving-detailed-' . $record->receiving_no . '.xlsx'
        );
    }

    public function storeReceiving(Request $request)
    {
        try {
            $validated = $request->validate([
                'replenishment_id' => ['required', 'integer', 'exists:uniform_replenishments,id'],
                'date_received' => ['required', 'date'],'crf_no' => ['required', 'string','max:100',
                Rule::unique('uniform_receivings','crf_no')
                ->where(function ($query){
                    return $query ->where('status', '!=', 'Cancelled');
                }),
                ],
                'document_no' => ['required', 'string', 'max:100',
                 Rule::unique('uniform_receivings','document_no')
                 ->where(function ($query){
                    return $query ->where('status', '!=' , 'Cancelled');
                 }),
                ],
                'checker_name' => ['required', 'string', 'max:255'],
                'transmitted_by' => ['nullable', 'string', 'max:255'],
                'remarks' => ['nullable', 'string', 'max:2000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.replenishment_item_id' => [
                    'required', 'integer', 'distinct', 'exists:uniform_replenishment_items,id',
                ],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
                'items.*.remarks' => ['nullable', 'string', 'max:1000'],
            ]);

            $record = DB::transaction(function () use ($request, $validated) {
                $replenishment = UniformReplenishment::with('items')
                    ->lockForUpdate()
                    ->findOrFail($validated['replenishment_id']);

                if (!in_array($replenishment->status, ['Posted', 'Partially Received'], true)) {
                    throw ValidationException::withMessages([
                        'replenishment_id' => 'Only posted requests with remaining quantities can be received.',
                    ]);
                }

                $replenishmentItems = $replenishment->items->keyBy('id');
                foreach ($validated['items'] as $item) {
                    $requestItem = $replenishmentItems->get($item['replenishment_item_id']);
                    if (!$requestItem || (float) $item['quantity'] > $requestItem->remaining_qty) {
                        throw ValidationException::withMessages([
                            'items' => 'A receiving quantity exceeds the approved remaining quantity.',
                        ]);
                    }
                }

                $record = UniformReceiving::create([
                    'replenishment_id' => $replenishment->id,
                    'supplier_id' => $replenishment->supplier_id,
                    'date_received' => $validated['date_received'],
                    'crf_no' => $validated['crf_no'],
                    'document_no' => $validated['document_no'],
                    'checker_name' => $validated['checker_name'],
                    'transmitted_by' => $validated['transmitted_by'] ?? null,
                    'status' => 'Unposted',
                    'total_amount' => collect($validated['items'])->sum(
                        fn ($item) => $item['quantity'] * $item['unit_price']
                    ),
                    'remarks' => $validated['remarks'] ?? null,
                    'prepared_by' => $this->userName($request),
                ]);

                foreach ($validated['items'] as $item) {
                    $requestItem = $replenishmentItems->get($item['replenishment_item_id']);
                    $record->items()->create([
                        ...$item,
                        'uniform_item_id' => $requestItem->uniform_item_id,
                    ]);
                }

                return $record;
            });

            return $this->sendResponse([
                'message' => 'Receiving transaction created successfully',
                'record' => $record->load(['supplier', 'replenishment', 'items.item']),
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function reviewReceiving(Request $request, int $id)
{
    try {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'exists:uniform_receiving_items,id'
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0'
            ],
        ]);

        DB::transaction(function () use ($request, $validated, $id) {

            $record = UniformReceiving::with('items')
                ->where('status', 'Unposted')
                ->lockForUpdate()
                ->findOrFail($id);


            foreach ($validated['items'] as $item) {

                $receivingItem = $record->items()
                    ->where('id', $item['id'])
                    ->firstOrFail();

                $receivingItem->update([
                    'quantity' => $item['quantity'],
                ]);
            }
            $record->update([
                'status' => 'Reviewed',
                'reviewed_by' => $this->userName($request),
                'reviewed_at' => now(),
            ]);
        });


        return $this->sendResponse([
            'message' => 'Receiving transaction reviewed'
        ]);

    } catch (Exception $e) {

        return $this->sendError([
            'message' => $e->getMessage()
        ]);
    }
}

    public function postReceiving(Request $request, int $id)
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $receiving = UniformReceiving::with(['items.item', 'supplier'])
                    ->where('status', 'Reviewed')
                    ->lockForUpdate()
                    ->findOrFail($id);
                UniformPeriodLock::ensureDateIsOpen($receiving->date_received, UniformPeriodLock::TYPE_RECEIVING);
                $replenishment = UniformReplenishment::lockForUpdate()
                    ->findOrFail($receiving->replenishment_id);

                foreach ($receiving->items as $receivingItem) {
                    $requestItem = UniformReplenishmentItem::lockForUpdate()
                        ->findOrFail($receivingItem->replenishment_item_id);

                    if ((float) $receivingItem->quantity > $requestItem->remaining_qty) {
                        throw ValidationException::withMessages([
                            'items' => "{$receivingItem->item?->description} exceeds the remaining quantity.",
                        ]);
                    }

                    $requestItem->increment('received_qty', $receivingItem->quantity);
                    UniformItem::whereKey($receivingItem->uniform_item_id)
                        ->lockForUpdate()
                        ->increment('stock_on_hand', $receivingItem->quantity);

                    UniformItemSupplier::updateOrCreate(
                        [
                            'uniform_item_id' => $receivingItem->uniform_item_id,
                            'vendor_no' => $receiving->supplier?->vendor_number,
                        ],
                        [
                            'vendor_name' => $receiving->supplier?->vendor_name,
                            'unit_price' => $receivingItem->unit_price,
                        ]
                    );
                }

                $hasRemaining = $replenishment->items()
                    ->whereColumn('received_qty', '<', 'approved_qty')
                    ->exists();
                $replenishment->update([
                    'status' => $hasRemaining ? 'Partially Received' : 'Fully Received',
                ]);
                $receiving->update([
                    'status' => 'Posted',
                    'posted_by' => $this->userName($request),
                    'posted_at' => now(),
                ]);
            });

            return $this->sendResponse([
                'message' => 'Receiving posted and Item Masterlist stock updated',
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function cancelReceiving(Request $request, int $id)
    {
        try {
            $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
            $record = UniformReceiving::whereIn('status', ['Unposted', 'Reviewed'])->findOrFail($id);
            $record->update([
                'status' => 'Cancelled',
                'cancelled_by' => $this->userName($request),
                'cancelled_at' => now(),
                'cancelled_reason' => $validated['reason'],
            ]);

            return $this->sendResponse(['message' => 'Receiving transaction cancelled']);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function transitionReplenishment(Request $request, int $id, string $from, string $to)
    {
        try {
            $record = UniformReplenishment::where('status', $from)->lockForUpdate()->findOrFail($id);
            $values = ['status' => $to];
            if ($to === 'Reviewed') {
                $values['reviewed_by'] = $this->userName($request);
                $values['reviewed_at'] = now();
            } else {
                UniformPeriodLock::ensureDateIsOpen($record->requested_date, UniformPeriodLock::TYPE_RECEIVING);
                $values['posted_by'] = $this->userName($request);
                $values['posted_at'] = now();
            }
            $record->update($values);

            return $this->sendResponse(['message' => "Replenishment request {$to}"]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function formatReplenishmentLines(Supplier $supplier, $items)
    {
        return $items->map(function (UniformItem $item) use ($supplier) {
            $quantity = (float) ($item->minimum_rpl ?? 1);

            return [
                'uniform_item_id' => $item->id,
                'item' => $item,
                'requested_qty' => $quantity,
                'approved_qty' => $quantity,
                'unit_price' => $this->supplierPrice($item, $supplier) ?? (float) ($item->standard_unit_price ?? 0),
                'remarks' => '',
            ];
        })->values();
    }

    private function supplierPrice(UniformItem $item, Supplier $supplier): ?float
    {
        $price = $item->suppliers
            ->firstWhere('vendor_no', $supplier->vendor_number)
            ?->unit_price;

        return $price === null ? null : (float) $price;
    }

    private function sheetRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $row) {
            if (collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));
    }

    private function firstHeaderIndex(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $index = array_search($name, $headers, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function numericCell(array $row, ?int $index, $default): float
    {
        if ($index === null || !isset($row[$index]) || trim((string) $row[$index]) === '') {
            return (float) $default;
        }

        return (float) str_replace(',', '', (string) $row[$index]);
    }

    private function userName(Request $request): ?string
    {
        return $request->user()?->proper_full_name;
    }
}
