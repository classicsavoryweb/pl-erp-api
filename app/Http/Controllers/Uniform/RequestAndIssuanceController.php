<?php

namespace App\Http\Controllers\Uniform;


use App\Http\Controllers\Controller;
use App\Exports\Maintenance\RequestAndIssuanceExport;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Models\RequestAndIssuance;
use App\Models\RequestAndIssuanceItems;
use App\Models\UniformItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class RequestAndIssuanceController extends Controller
{
   public function getUniformRequestAndIssuance(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $issuance = RequestAndIssuance::with('items','employee')->latest("id");

            if ($request->has(["column_type", "key_word"])) {
                $issuance = $issuance->where(
                    $request->column_type,
                    "LIKE",
                    "%" . $request->key_word . "%"
                );
            }

            if ($request->has("export") && $request->export) {
                $issuance = $issuance->get();
                return Excel::download(
                    new RequestAndIssuanceExport($issuance),
                    "suppliers.xls"
                );
            }

            $issuance = $issuance->paginate($perPage);

            return $this->sendResponse($issuance);

        } catch (Exception $e) {
            return $this->sendError([
                "message" => $e->getMessage()
            ]);
        }
    }
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:Unposted,Reviewing,Posted,Cancelled'],
        ]);
        $next = $request->status;

        $issuance = DB::transaction(function () use ($request, $id, $next) {
            $issuance = RequestAndIssuance::lockForUpdate()->findOrFail($id);
            $current = $issuance->status;

            $allowedTransitions = [
                'Unposted' => ['Reviewing', 'Cancelled'],
                'Reviewing' => ['Posted', 'Cancelled'],
                'Posted' => [],
                'Cancelled' => [],
            ];

            if (!in_array($next, $allowedTransitions[$current] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Invalid status transition from {$current} to {$next}.",
                ]);
            }

            $updateData = ['status' => $next];

            if ($next === 'Reviewing' && !$issuance->date_reviewed) {
                $updateData['date_reviewed'] = now();
                $updateData['reviewed_by'] = optional($request->user())->proper_full_name;
            }

            if ($next === 'Posted' && !$issuance->date_posted) {
                $issuance->load('items');

                if ($issuance->items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'A request must contain at least one item before posting.',
                    ]);
                }

                foreach ($issuance->items as $item) {
                    $quantityToDeduct = (int) $item->requested_quantity;

                    if ($quantityToDeduct < 1) {
                        throw ValidationException::withMessages([
                            'items' => "Invalid requested quantity for {$item->description}.",
                        ]);
                    }

                    $uniformItem = UniformItem::where('item_no', $item->uniform_item_no)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($uniformItem->stock_on_hand < $quantityToDeduct) {
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$uniformItem->description}. "
                                . "Available: {$uniformItem->stock_on_hand}.",
                        ]);
                    }

                    $uniformItem->decrement('stock_on_hand', $quantityToDeduct);
                }

                $updateData['date_posted'] = now();
                $updateData['posted_by'] = optional($request->user())->proper_full_name;
            }

            $issuance->update($updateData);

            return $issuance->fresh('items');
        });

        return $this->sendResponse([
            'message' => 'Status updated successfully.',
            'record' => $issuance,
        ]);
    }
   public function cancelUniformRequestAndIssuance(Request $request, $id)
{
    $request->validate([
        'cancel_reason' => ['required', 'string', 'max:1000'],
    ]);

    $issuance = RequestAndIssuance::findOrFail($id);

    if (in_array($issuance->status, ['Posted', 'Cancelled'])) {
        return $this->sendError([
            'message' => 'This request cannot be cancelled.'
        ]);
    }

    $issuance->update([
        'status' => 'Cancelled',
        'cancel_reason' => $request->cancel_reason,
        'date_cancelled' => now(),
        'cancelled_by' => optional($request->user())->proper_full_name,
    ]);

    return $this->sendResponse($issuance, 'Request cancelled successfully.');
}
        
   public function createUniformRequestAndIssuance(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => ['required', 'string', 'max:100'],
                'employee_name' => ['required', 'string', 'max:255'],
                'issuance_type' => ['required', Rule::in(['Authority To Deduct', 'Free Uniform'])],
                'branch' => ['required', 'string', 'max:255'],
                'position' => ['required', 'string', 'max:255'],
                'date_hired' => ['required', 'date'],
                'items' => ['required', 'array', 'min:1', 'max:20'],
                'items.*.item_no' => [
                    'required',
                    'string',
                    'distinct',
                    'exists:uniform_items,item_no',
                ],
                'items.*.requested_quantity' => ['required', 'integer', 'min:1'],
                'items.*.item_remarks' => ['nullable', 'string', 'max:1000'],
            ]);

            $issuance = DB::transaction(function () use ($request, $validated) {
                $itemNumbers = collect($validated['items'])->pluck('item_no');
                $uniformItems = UniformItem::with(['size', 'gender'])
                    ->whereIn('item_no', $itemNumbers)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('item_no');

                $issuance = RequestAndIssuance::create([
                    'employee_id' => $validated['employee_id'],
                    'employee_name' => $validated['employee_name'],
                    'issuance_type' => $validated['issuance_type'],
                    'branch' => $validated['branch'],
                    'position' => $validated['position'],
                    'date_hired' => $validated['date_hired'],
                    'total_amount' => 0,
                    'date_requested' => now(),
                    'status' => 'Unposted',
                    'sent_email' => 'No',
                    'created_by' => optional($request->user())->proper_full_name,
                    'request_number' => $this->generateItemNo($validated['issuance_type']),
                ]);

                $grandTotal = 0;

                foreach ($validated['items'] as $itemData) {
                    $uniformItem = $uniformItems->get($itemData['item_no']);
                    $requestedQuantity = (int) $itemData['requested_quantity'];

                    if (!$uniformItem || strtolower((string) $uniformItem->status) !== 'active') {
                        throw ValidationException::withMessages([
                            'items' => "Uniform item {$itemData['item_no']} is not active.",
                        ]);
                    }

                    if ($uniformItem->stock_on_hand < $requestedQuantity) {
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$uniformItem->description}. "
                                . "Available: {$uniformItem->stock_on_hand}.",
                        ]);
                    }

                    $unitPrice = (float) $uniformItem->standard_unit_price;
                    $totalAmount = $requestedQuantity * $unitPrice;
                    $originalStock = (int) $uniformItem->stock_on_hand;
                    $grandTotal += $totalAmount;

                    RequestAndIssuanceItems::create([
                        'requested_number_id' => $issuance->request_number,
                        'description' => $uniformItem->description,
                        'size' => $uniformItem->size?->description ?? '',
                        'gender' => $uniformItem->gender?->code ?? '',
                        'stock_on_hand' => $originalStock,
                        'requested_quantity' => $requestedQuantity,
                        'remaining_quantity' => $originalStock - $requestedQuantity,
                        'uom' => $uniformItem->standard_unit,
                        'unit_price' => $unitPrice,
                        'total_amount' => $totalAmount,
                        'item_remarks' => $itemData['item_remarks'] ?? null,
                        'uniform_item_no' => $uniformItem->item_no,
                    ]);
                }

                $issuance->update(['total_amount' => $grandTotal]);

                return $issuance->fresh('items');
            });

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
            ]);
        }

        return $this->sendResponse([
            'message' => 'Uniform request created successfully.',
            'record' => $issuance,
        ]);
    }
    
    private function generateItemNo(string $issuanceType): string
    {
        $prefix = match ($issuanceType) {
            'Authority To Deduct' => 'ATD',
            'Free Uniform' => 'FR',
            default => throw new \InvalidArgumentException(
                "Invalid issuance type: {$issuanceType}"
            ),
        };

        $last = RequestAndIssuance::where(
                'request_number',
                'LIKE',
                "{$prefix}-URN%"
            )
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $next = $last
            ? ((int) substr($last->request_number, -8)) + 1
            : 1;
        return $prefix . '-URN' . str_pad($next, 8, '0', STR_PAD_LEFT);
    }
}
 
