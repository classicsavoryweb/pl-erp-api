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
        
        $issuance = RequestAndIssuance::findOrFail($id);

        $current = $issuance->status;
        $next = $request->status;

        $allowedTransitions = [
            'Unposted' => ['Reviewing', 'Cancelled'],
            'Reviewing' => ['Posted', 'Cancelled'],
            'Posted' => [],
            'Cancelled' => [],
        ];

        if (!in_array($next, $allowedTransitions[$current])) {
            return $this->sendError([
                'message' => "Invalid status transition from {$current} to {$next}"
            ]);
        }

        $updateData = [
            'status' => $next,
        ];
        
        if($next === 'Reviewing' && !$issuance->date_reviewed){
            $updateData['date_reviewed'] = now();
            $updateData['reviewed_by'] = optional($request->user())->proper_full_name;
        }

       if ($next === 'Posted' && !$issuance->date_posted) {

            $updateData['date_posted'] = now();
            $updateData['posted_by'] = optional($request->user())->proper_full_name;

            DB::transaction(function () use ($issuance) {

                $issuance->load('items');

                foreach ($issuance->items as $item) {

                    $quantityToDeduct = $item->requested_quantity;

                    if ($quantityToDeduct <= 0) {
                        continue;
                    }

                    $uniformItem = UniformItem::where('item_no', $item->uniform_item_no)
                        ->lockForUpdate()
                        ->first();

                    if (!$uniformItem) {
                        throw new \Exception("Uniform item not found: {$item->uniform_item_no}");
                    }

                    if ($uniformItem->stock_on_hand < $quantityToDeduct) {
                        throw new \Exception(
                            "Insufficient stock for {$uniformItem->item_no}. Available: {$uniformItem->stock_on_hand}"
                        );
                    }

                    $uniformItem->decrement('stock_on_hand', $quantityToDeduct);
                }
            });
        }         
        $issuance->update($updateData);
        return $this->sendResponse($issuance, "Status updated successfully");        
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
            DB::transaction(function () use ($request) {
        $issuanceData = $request->validate([
            'employee_id'   => ['required'],
            'employee_name' => ['required', 'string', 'max:255'],
            'issuance_type' => ['required'],
            'branch'        => ['required'],
            'position'      => ['required'],
            'date_hired'    => ['required'],
            'status'        => ['required'],
            'sent_email'    => ['required'],
            'payroll_entry' => ['nullable'],
            'payroll_sent'  => ['nullable'],
        ]);

        $issuance = RequestAndIssuance::create([
            ...$issuanceData,
            'total_amount'   => 0,
            'date_requested' => now(),
            'created_by'     => optional($request->user())->proper_full_name,
            'request_number' => $this->generateItemNo($issuanceData['issuance_type']),
        ]);

        $grandTotal = 0;

        foreach ($request->items as $item) {

            $uniformItem = UniformItem::where('item_no', $item['item_no'])
                ->lockForUpdate()
                ->firstOrFail();
            $unitPrice = (float) ($item['unit_price'] ?? $item['standard_unit_price'] ?? 0);
            $totalAmount = (float) $item['requested_quantity'] * $unitPrice;

           $grandTotal += $totalAmount;

            $originalStock = $uniformItem->stock_on_hand;
            $remainingQuantity = $originalStock - $item['requested_quantity'];

            RequestAndIssuanceItems::create([
                'requested_number_id' => $issuance->request_number,
                'description'         => $item['description'],
                'size'                => $item['size'],
                'gender'              => $item['gender'],
                'stock_on_hand'       => $originalStock,
                'requested_quantity'  => $item['requested_quantity'],
                'remaining_quantity'  => $remainingQuantity,
                'uom'                 => $item['uom'],
                'unit_price'          => $unitPrice,
                'total_amount'        => $totalAmount,
                'item_remarks'        => $item['item_remarks'] ?? null,
                'uniform_item_no'     => $item['item_no'],
            ]);
        }

        // Update parent total with sum of all item totals
        $issuance->update([
            'total_amount' => $grandTotal,
        ]);
    });

        } catch (\Exception $e) {

            return $this->sendError([
                'message' => $e->getMessage(),
            ]);
        }
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
 