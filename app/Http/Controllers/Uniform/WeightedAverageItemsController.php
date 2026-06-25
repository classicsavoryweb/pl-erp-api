<?php

namespace App\Http\Controllers\Uniform;

use App\Http\Controllers\Controller;
use App\Models\UniformReceivingItem;
use App\Models\WeightedAverageItems;

class WeightedAverageItemsController extends Controller
{
    public function getWeightAverageById($id)
    {
        try {
            $weighted = WeightedAverageItems::with('weightedAverage', 'uniform')
                ->where('id', $id)
                ->first();

            if (!$weighted) {
                return $this->sendError([
                    'message' => 'Weighted Average not found'
                ], 404);
            }

            $dateFrom = $weighted->weightedAverage?->date_from;
            $dateTo = $weighted->weightedAverage?->date_to;

            $receivingDetails = collect();

            if ($weighted->uniform && $dateFrom && $dateTo) {
                $receivingDetails = UniformReceivingItem::with('receiving.supplier')
                    ->where('uniform_item_id', $weighted->uniform->id)
                    ->whereHas('receiving', function ($query) use ($dateFrom, $dateTo) {
                        $query->where('status', 'Posted')
                            ->whereBetween('posted_at', [
                                $dateFrom . ' 00:00:00',
                                $dateTo . ' 23:59:59',
                            ]);
                    })
                    ->get()
                    ->map(function ($item) use ($weighted) {
                        return [
                            'date_received' => $item->receiving?->date_received,
                            'total_quantity' => (float) $item->quantity,
                            'standard_uom' => $weighted->standard_uom,
                            'new_standard_price' => (float) $item->unit_price,
                            'total_amount' => (float) $item->total_amount,
                            'supplier' => $item->receiving?->supplier?->vendor_name,
                            'crf_no' => $item->receiving?->crf_no,
                            'document_no' => $item->receiving?->document_no,
                            'receiving_no' => $item->receiving?->receiving_no,
                            'transmitted_by' => $item->receiving?->transmitted_by,
                        ];
                    });
            }

            $weighted->setAttribute('receiving_details', $receivingDetails);

            return $this->sendResponse($weighted);

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

}
