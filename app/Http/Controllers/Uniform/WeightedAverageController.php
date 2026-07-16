<?php

namespace App\Http\Controllers\Uniform;

use App\Http\Controllers\Controller;
use App\Exports\Uniform\WeightedAverageDetailedExport;
use App\Exports\Uniform\WeightedAverageExport;
use App\Models\WeightedAverage;
use App\Models\WeightedAverageItems;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\UniformItem;
use App\Models\UniformPeriodLock;
use Illuminate\Support\Facades\Log;
use App\Models\UniformReceivingItem;

class WeightedAverageController extends Controller
{
    public function getWeightAverage(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $weighted = WeightedAverage::with('items')->latest("id");

            if ($request->has(["column_type", "key_word"])) {
                $weighted = $weighted->where(
                    $request->column_type,
                    "LIKE",
                    "%" . $request->key_word . "%"
                );
            }

            if ($request->has("export") && $request->export) {
                $weighted = $weighted->get();
                return Excel::download(
                    new WeightedAverageExport($weighted),
                    "weighted.xls"
                );
            }

            $weighted = $weighted->paginate($perPage);

            return $this->sendResponse($weighted);

        } catch (Exception $e) {
            return $this->sendError([
                "message" => $e->getMessage()
            ]);
        }
    }
      public function exportAllWeightAverage(Request $request)
    {
        $request['export'] = true;
        return $this->getWeightAverage($request);
    }

    public function exportWeightAverageDetails($id)
    {
        $weighted = WeightedAverage::with('items')->findOrFail($id);

        return Excel::download(
            new WeightedAverageDetailedExport($weighted),
            'weighted-average-detailed-' . $weighted->weighted_id . '.xlsx'
        );
    }
    public function getReceivingItems(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'tag' => ['nullable', Rule::in(['Items with Purchases', 'Item with Purchases', 'Items without Purchases', 'Check All'])],
        ]);

        $postedReceivings = UniformReceivingItem::query()
            ->select([
                'uniform_receiving_items.uniform_item_id',
                DB::raw('SUM(uniform_receiving_items.quantity) as total_quantity'),
                DB::raw('SUM(uniform_receiving_items.quantity * uniform_receiving_items.unit_price) as total_amount'),
            ])
            ->join('uniform_receivings', 'uniform_receivings.id', '=', 'uniform_receiving_items.receiving_id')
            ->where('uniform_receivings.status', 'Posted')
            ->whereBetween('uniform_receivings.posted_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59',
            ])
            ->groupBy('uniform_receiving_items.uniform_item_id');

        $items = UniformItem::query()
            ->leftJoinSub($postedReceivings, 'purchases', function ($join) {
                $join->on('uniform_items.id', '=', 'purchases.uniform_item_id');
            })
            ->select([
                'uniform_items.id',
                'uniform_items.item_no',
                'uniform_items.description',
                'uniform_items.standard_unit',
                'uniform_items.standard_unit_price',
                DB::raw('COALESCE(purchases.total_quantity, 0) as total_quantity'),
                DB::raw('COALESCE(purchases.total_amount, 0) as total_amount'),
            ])
            ->when($request->tag === 'Items without Purchases', function ($query) {
                $query->whereRaw('COALESCE(purchases.total_quantity, 0) = 0');
            })
            ->when($request->tag !== 'Check All' && $request->tag !== 'Items without Purchases', function ($query) {
                $query->whereRaw('COALESCE(purchases.total_quantity, 0) > 0');
            })
            ->orderBy('uniform_items.item_no')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(function ($item) {
                $totalQuantity = (float) $item->total_quantity;
                $totalAmount = (float) $item->total_amount;
                $currentPrice = (float) $item->standard_unit_price;
                $newStandardPrice = $totalQuantity > 0
                ? round($totalAmount / $totalQuantity, 2)
                : null;

                return [
                    'item_no' => $item->item_no,
                    'uniform_item_no' => $item->item_no,
                    'description' => $item->description,
                    'standard_uom' => $item->standard_unit,
                    'standard_uom_two' => $item->standard_unit,
                    'current_price' => round($currentPrice, 2),
                    'total_quantity' => round($totalQuantity, 2),
                    'total_amount' => round($totalAmount, 2),
                    'new_standard_price' => round($newStandardPrice, 2),
                    'other_charges' => 0,
                    'standard_difference' => $newStandardPrice === null
                        ? '-'
                        : round($newStandardPrice - $currentPrice, 2),
                    'reason' => '',
                ];
            })->values(),
        ]);
    }
    public function updateWeightedStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:Unposted,Reviewed,Posted,Cancelled'],
        ]);
        $next = $request->status;

        $weighted = DB::transaction(function () use ($request, $id, $next) {
            $weighted = WeightedAverage::lockForUpdate()->findOrFail($id);
            $current = $weighted->status;
            $allowedTransitions = [
                'Unposted' => ['Reviewed', 'Cancelled'],
                'Reviewing' => ['Posted', 'Cancelled'],
                'Reviewed' => ['Posted', 'Cancelled'],
                'Posted' => [],
                'Cancelled' => [],
            ];
            if (!in_array($next, $allowedTransitions[$current] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Invalid status transition from {$current} to {$next}.",
                ]);
            }
            $updateData = ['status' => $next];
            if ($next === 'Reviewed' && !$weighted->reviewed_at) {
                $updateData['reviewed_at'] = now();
                $updateData['reviewed_by'] = optional($request->user())->proper_full_name;
            }
            if ($next === 'Posted' && !$weighted->posted_at) {
                $weighted->load('items');
                UniformPeriodLock::ensureDateIsOpen($weighted->date_posted, UniformPeriodLock::TYPE_RECEIVING);

                if ($weighted->items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'A request must contain at least one item before posting.',
                    ]);
                }

                foreach ($weighted->items as $item) {
                    UniformItem::where('item_no', $item->uniform_item_no)
                        ->lockForUpdate()
                        ->update(['standard_unit_price' => $item->new_standard_price]);
                }

                $updateData['posted_at'] = now();
                $updateData['posted_by'] = optional($request->user())->proper_full_name;
            }
            $weighted->update($updateData);

            return $weighted->fresh('items');
        });

        return $this->sendResponse([
            'message' => 'Status updated successfully.',
            'record' => $weighted,
        ]);
    }


   public function updatePrice(Request $request)
    {
        $request->validate([
            'item_no' => 'required',
            'standard_unit_price' => 'required|numeric',
        ]);

        $item = UniformItem::where('item_no', $request->item_no)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.'
            ], 404);
        }

        $item->standard_unit_price = $request->standard_unit_price;
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Price updated successfully.'
        ]);
    }

    public function createWeightedAverage(Request $request)
    {
        try {
            Log::info('Weighted Average CREATE - Request received', [
                'payload' => $request->all()
            ]);

            $validated = $request->validate([
                'date_posted' => ['required', 'date','before_or_equal:today'],
                'date_from' => ['required', 'date','before_or_equal:today'],
                'date_to' => ['required', 'date','before_or_equal:today', 'after_or_equal:date_from'],
                'overall_remarks' => ['nullable', 'string', 'max:1000'],
                'tag' => ['required','string'],


                'items' => ['required', 'array', 'min:1'],
                'items.*.item_no' => ['required', 'string'],
                'items.*.item_remarks' => ['nullable', 'string'],
                'items.*.reason' => ['nullable', 'string'],
                'items.*.total_amount' => ['required','numeric'],
                'items.*.total_quantity' => ['required','numeric'],
                'items.*.current_price' => ['required' , 'numeric'],
                'items.*.other_charges' => ['nullable', 'numeric', 'min:0'],
                'items.*.standard_difference' => ['required', 'numeric'],
                'items.*.new_standard_price' => ['required', 'numeric', 'min:0'],
            ]);

            $weighted = DB::transaction(function () use ($request, $validated) {
                if (date('Y-m', strtotime($validated['date_from'])) !== date('Y-m', strtotime($validated['date_to']))) {
                    throw ValidationException::withMessages([
                        'date_to' => 'Date range must be in the same month and year.',
                    ]);
                }

                $hasExistingRange = WeightedAverage::where('date_from', $validated['date_from'])
                    ->where('date_to', $validated['date_to'])
                    ->where('status', '!=', 'Cancelled')
                    ->lockForUpdate()
                    ->exists();

                if ($hasExistingRange) {
                    throw ValidationException::withMessages([
                        'date_from' => 'Date range already exists.',
                    ]);
                }

                $itemNumbers = collect($validated['items'])->pluck('item_no');

                $uniformItems = UniformItem::whereIn('item_no', $itemNumbers)
                    ->get()
                    ->keyBy('item_no');

                $weighted = WeightedAverage::create([
                    'weighted_id' => $this->generateItemNo(),
                    'tag' => $validated['tag'],
                    'date_posted' => $validated['date_posted'],
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'status' => 'Unposted',
                    'overall_remarks' => $validated['overall_remarks'] ?? null,
                    'prepared_by' => optional($request->user())->proper_full_name,
                    'prepared_at' => now(),
                ]);
                foreach ($validated['items'] as $itemData) {
                    $uniformItem = $uniformItems->get(trim($itemData['item_no']));

                    if (!$uniformItem) {
                        Log::warning('Uniform item not found', [
                            'item_no' => $itemData['item_no']
                        ]);
                        continue;
                    }

                    $totalQuantity = (float) $itemData['total_quantity'];
                    $totalAmount = (float) $itemData['total_amount'];
                    $otherCharges = (float) ($itemData['other_charges'] ?? 0);

                    if ($otherCharges > 0 && empty(trim($itemData['item_remarks'] ?? $itemData['reason'] ?? ''))) {
                        throw ValidationException::withMessages([
                            'items' => 'Reason is required when additional charge is not zero.',
                        ]);
                    }

                    $newStandardPrice = $totalQuantity > 0 ? ($totalAmount / $totalQuantity) + $otherCharges : $otherCharges;
                    $standardDifference = $newStandardPrice - (float) $itemData['current_price'];

                    WeightedAverageItems::create([
                        'weighted_id_number' => $weighted->weighted_id,
                        'uniform_item_no' => $uniformItem->item_no,
                        'description' => $uniformItem->description,
                        'standard_uom' => $uniformItem->standard_unit,
                        'current_price' => $itemData['current_price'],
                        'total_quantity' => $totalQuantity,
                        'standard_uom_two' => $uniformItem->standard_unit,
                        'total_amount' => $totalAmount,
                        'new_standard_price' => $newStandardPrice,
                        'other_charges' => $otherCharges,
                        'standard_difference' => $standardDifference,
                        'reason' => $itemData['item_remarks'] ?? $itemData['reason'] ?? null,
                    ]);
                }
                return $weighted->load('items');
            });

            return response()->json([
                'success' => true,
                'message' => 'Weighted Average Created Successfully',
                'data' => $weighted
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function generateItemNo(): string
    {
        $last = WeightedAverage::where('weighted_id', 'LIKE', 'WA%')
            ->orderByDesc('weighted_id')
            ->lockForUpdate()
            ->first();

        $next = $last
            ? ((int) substr($last->weighted_id, 2)) + 1
            : 1;

        return 'WA' . str_pad($next, 7, '0', STR_PAD_LEFT);
    }

}
