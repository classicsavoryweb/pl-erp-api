<?php 
namespace App\Http\Services;

use App\Models\RequestAndIssuance;
use App\Models\UniformItem;
use App\Models\Employee;
use App\Models\RequestAndIssuanceItems;
use App\Http\Services\FreeUniformLimitService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;



class UniformRequestItemService
{
    public function replaceItems(
        RequestAndIssuance $issuance,
        array $items,
        $uniformItems
    ): float {
        RequestAndIssuanceItems::where('requested_number_id', $issuance->request_number)->delete();

        $grandTotal = 0;

        $oldItems = $issuance->items->keyBy('uniform_item_no');
        foreach ($items as $itemData) {
            $itemNo = $itemData['item_no'];
            $uniformItem = $uniformItems->get($itemNo);
            $requestedQuantity = (int) $itemData['requested_quantity'];
            $oldItem = $oldItems->get($itemNo);
            $unitPrice = $oldItem
                ? (float) $oldItem->unit_price
                : (float) $uniformItem->standard_unit_price;

            $totalAmount = $requestedQuantity * $unitPrice;

            if (!$uniformItem || strtolower((string) $uniformItem->status) !== 'active') {
                throw ValidationException::withMessages([
                    'items' => "Uniform item {$itemNo} is not active.",
                ]);
            }

            if ($uniformItem->stock_on_hand < $requestedQuantity) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for {$uniformItem->description}. "
                        . "Available: {$uniformItem->stock_on_hand}.",
                ]);
            }
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

            $uniformItem->decrement('stock_on_hand', $requestedQuantity);
            $uniformItem->refresh();
        }

        return $grandTotal;
    }
    
}
