<?php

namespace App\Http\Controllers\Uniform;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\UniformItem;
use App\Models\UniformReceiving;
use App\Models\UniformReplenishment;
use App\Models\UniformReplenishmentItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                    'request_no' => $this->nextNumber(UniformReplenishment::class, 'request_no', 'RPL'),
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
        return $this->transitionReplenishment($request, $id, 'Unposted', 'Reviewed');
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

    public function receivableReplenishments()
    {
        try {
            $records = UniformReplenishment::with([
                'supplier',
                'items' => fn ($query) => $query->whereColumn('received_qty', '<', 'approved_qty'),
                'items.item.size',
                'items.item.gender',
            ])
                ->whereIn('status', ['Posted', 'Partially Received'])
                ->whereHas('items', fn ($query) => $query->whereColumn('received_qty', '<', 'approved_qty'))
                ->latest('id')
                ->get();

            return $this->sendResponse($records);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
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

    public function storeReceiving(Request $request)
    {
        try {
            $validated = $request->validate([
                'replenishment_id' => ['required', 'integer', 'exists:uniform_replenishments,id'],
                'date_received' => ['required', 'date'],
                'crf_no' => ['required', 'string', 'max:100', 'unique:uniform_receivings,crf_no'],
                'document_no' => ['required', 'string', 'max:100', 'unique:uniform_receivings,document_no'],
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
                    'receiving_no' => $this->nextNumber(UniformReceiving::class, 'receiving_no', 'RCV'),
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
            $record = UniformReceiving::where('status', 'Unposted')->findOrFail($id);
            $record->update([
                'status' => 'Reviewed',
                'reviewed_by' => $this->userName($request),
                'reviewed_at' => now(),
            ]);

            return $this->sendResponse(['message' => 'Receiving transaction reviewed']);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function postReceiving(Request $request, int $id)
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $receiving = UniformReceiving::with(['items.item'])
                    ->where('status', 'Reviewed')
                    ->lockForUpdate()
                    ->findOrFail($id);
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
            $record = UniformReplenishment::where('status', $from)->findOrFail($id);
            $values = ['status' => $to];
            if ($to === 'Reviewed') {
                $values['reviewed_by'] = $this->userName($request);
                $values['reviewed_at'] = now();
            } else {
                $values['posted_by'] = $this->userName($request);
                $values['posted_at'] = now();
            }
            $record->update($values);

            return $this->sendResponse(['message' => "Replenishment request {$to}"]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function nextNumber(string $model, string $column, string $prefix): string
    {
        $latest = $model::where($column, 'LIKE', "{$prefix}%")
            ->lockForUpdate()
            ->get([$column])
            ->map(fn ($record) => (int) substr($record->{$column}, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($latest + 1), 6, '0', STR_PAD_LEFT);
    }

    private function userName(Request $request): ?string
    {
        return $request->user()?->proper_full_name;
    }
}
