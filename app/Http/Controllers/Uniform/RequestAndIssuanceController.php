<?php

namespace App\Http\Controllers\Uniform;


use App\Http\Controllers\Controller;
use App\Exports\Uniform\RequestAndIssuanceExport;
use App\Exports\Uniform\RequestAndIssuanceDetailedExport;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Models\RequestAndIssuance;
use App\Models\RequestAndIssuanceItems;
use App\Models\UniformItem;
use App\Models\Employee;
use App\Models\UniformPeriodLock;
use App\Models\UniformFreeUniformLimit;
use App\Models\Branch;
use App\Models\UniformPhysicalCount;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class RequestAndIssuanceController extends Controller
{
  public function getUniformRequestAndIssuance(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $issuance = RequestAndIssuance::with('items','branches')->latest("id");

    
            if ($request->filled('column_type') && $request->filled('key_word')) {

                if ($request->column_type === 'item_name') {
                    $issuance->whereHas('items', function ($query) use ($request) {
                        $query->where('description', 'LIKE', '%' . $request->key_word . '%');
                    });
                } else {
                    $issuance->where(
                        $request->column_type,
                        'LIKE',
                        '%' . $request->key_word . '%'
                    );
                }
            }

            if ($request->filled('status')) {
                $issuance->where("status", $request->status);
            }

            if ($request->filled('branch_id')) {
                $branch = Branch::find($request->branch_id);

                if ($branch) {
                    $issuance->where('branch', $branch->name);
                }
            }

            if ($request->has("export") && $request->export) {
                $issuance = $issuance->get();

                return Excel::download(
                    new RequestAndIssuanceExport($issuance),
                    "issuance.xls"
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
    public function exportRequestandIssuance (Request $request) {
        $request["export"] = true;

        return $this->getUniformRequestAndIssuance($request);
    }

    public function exportRequestAndIssuanceDetails($id)
    {
        $issuance = RequestAndIssuance::with('items')->findOrFail($id);

        return Excel::download(
            new RequestAndIssuanceDetailedExport($issuance),
            'request-and-issuance-detailed-' . $issuance->request_number . '.xlsx'
        );
    }
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:Unposted,Reviewed,Posted,Cancelled'],
        ]);
        $next = $request->status;

        $issuance = DB::transaction(function () use ($request, $id, $next) {
            $issuance = RequestAndIssuance::lockForUpdate()->findOrFail($id);
            $current = $issuance->status;

            $allowedTransitions = [
                'Unposted' => ['Reviewed', 'Cancelled'],
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

            if ($next === 'Reviewed' && !$issuance->date_reviewed) {
                $updateData['date_reviewed'] = now();
                $updateData['reviewed_by'] = optional($request->user())->proper_full_name;
            }

            if ($next === 'Posted' && !$issuance->date_posted) {
                $issuance->load('items');
                UniformPeriodLock::ensureDateIsOpen($issuance->date_request ?? now(), UniformPeriodLock::TYPE_ISSUANCE);

                if ($issuance->items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'A request must contain at least one item before posting.',
                    ]);
                }

                foreach ($issuance->items as $item) {
                    if ((int) $item->requested_quantity < 1) {
                        throw ValidationException::withMessages([
                            'items' => "Invalid requested quantity for {$item->description}.",
                        ]);
                    }
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

        $issuance = DB::transaction(function () use ($request, $id) {
            $issuance = RequestAndIssuance::with('items')->lockForUpdate()->findOrFail($id);

            if (in_array($issuance->status, ['Posted', 'Cancelled'])) {
                throw ValidationException::withMessages([
                    'status' => 'This request cannot be cancelled.',
                ]);
            }

            foreach ($issuance->items as $item) {
                UniformItem::where('item_no', $item->uniform_item_no)
                    ->lockForUpdate()
                    ->increment('stock_on_hand', (int) $item->requested_quantity);
            }

            $issuance->update([
                'status' => 'Cancelled',
                'cancel_reason' => $request->cancel_reason,
                'date_cancelled' => now(),
                'cancelled_by' => optional($request->user())->proper_full_name,
            ]);

            return $issuance->fresh('items');
        });

        return $this->sendResponse($issuance, 'Request cancelled successfully.');
    }

    public function reviewUniformRequestAndIssuance(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'employee_id' => ['required', 'string', 'max:100'],
                'employee_name' => ['required', 'string', 'max:255'],
                'issuance_type' => ['required', Rule::in(['Authority To Deduct', 'Free Uniform'])],
                'branch' => ['required', 'string', 'max:255'],
                'position' => ['required', 'string', 'max:255'],
                'date_hired' => ['required', 'date'],
                'confirm_free_uniform_duplicate' => ['sometimes', 'boolean'],
                'items' => ['required', 'array', 'min:1', 'max:20'],
                'items.*.item_no' => [
                    'nullable',
                    'string',
                    'distinct',
                    'exists:uniform_items,item_no',
                ],
                'items.*.uniform_item_no' => [
                    'nullable',
                    'string',
                    'distinct',
                    'exists:uniform_items,item_no',
                ],
                'items.*.requested_quantity' => ['required', 'integer', 'min:1'],
                'items.*.item_remarks' => ['nullable', 'string', 'max:1000'],
            ]);

            $validated['items'] = collect($validated['items'])->map(function ($item) {
                $item['item_no'] = $item['item_no'] ?? $item['uniform_item_no'] ?? null;
                return $item;
            })->all();

            if (collect($validated['items'])->contains(fn ($item) => empty($item['item_no']))) {
                throw ValidationException::withMessages([
                    'items' => 'Each request item must have a valid item number.',
                ]);
            }

            if (collect($validated['items'])->pluck('item_no')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Duplicate request items are not allowed.',
                ]);
            }

            $duplicateFreeUniform = $this->getDuplicateFreeUniformRequest($validated, (int) $id);

            if ($validated['issuance_type'] === 'Free Uniform') {

                $limit = UniformFreeUniformLimit::where('status', 'active')
                    ->whereDate('date_from', '<=', now())
                    ->whereDate('date_to', '>=', now())
                    ->latest('id')
                    ->first();

                if ($limit) {
                    $requestedQty = collect($validated['items'])->sum('requested_quantity');

                    if ($requestedQty > $limit->allowed_count) {
                        return $this->sendError([
                            'message' => "The total requested quantity ({$requestedQty}) exceeds the allowed free uniform limit of {$limit->allowed_count} item(s).",
                        ], 422);
                    }
                }
            }

            if ($duplicateFreeUniform && empty($validated['confirm_free_uniform_duplicate'])) {
                return $this->sendError([
                    'requires_confirmation' => true,
                    'confirmation_type' => 'free_uniform_duplicate',
                    'message' => 'This employee has already availed his/her free uniform for the current year',
                    'previous_request_number' => $duplicateFreeUniform->request_number,
                    'previous_request_date' => $duplicateFreeUniform->date_requested,
                ], 422);
            }

            $issuance = DB::transaction(function () use ($request, $id, $validated) {
                $issuance = RequestAndIssuance::with('items')->lockForUpdate()->findOrFail($id);

                if (!in_array($issuance->status, ['Unposted', 'Reviewed'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only unposted or reviewing requests can be reviewed.',
                    ]);
                }

                $oldItems = $issuance->items;
                $itemNumbers = collect($validated['items'])
                    ->pluck('item_no')
                    ->merge($oldItems->pluck('uniform_item_no'))
                    ->filter()
                    ->unique()
                    ->values();

                $uniformItems = UniformItem::with(['size', 'gender'])
                    ->whereIn('item_no', $itemNumbers)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('item_no');

                foreach ($oldItems as $oldItem) {
                    $uniformItem = $uniformItems->get($oldItem->uniform_item_no);

                    if ($uniformItem) {
                        $uniformItem->increment('stock_on_hand', (int) $oldItem->requested_quantity);
                        $uniformItem->refresh();
                    }
                }

                $employeeGender = Employee::where('employee_number', $validated['employee_id'])->value('gender');
                $grandTotal = $this->replaceRequestItems(
                    $issuance,
                    $validated['items'],
                    $uniformItems,
                    $employeeGender
                );

                $issuance->update([
                    'employee_id' => $validated['employee_id'],
                    'employee_name' => $validated['employee_name'],
                    'issuance_type' => $validated['issuance_type'],
                    'branch' => $validated['branch'],
                    'position' => $validated['position'],
                    'date_hired' => $validated['date_hired'],
                    'total_amount' => $grandTotal,
                    'status' => 'Reviewed',
                    'date_reviewed' => now(),
                    'reviewed_by' => optional($request->user())->proper_full_name,
                ]);

                return $issuance->fresh('items');
            });

            return $this->sendResponse([
                'message' => 'Uniform request reviewed successfully.',
                'record' => $issuance,
            ]);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
            ]);
        }
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
                'confirm_free_uniform_duplicate' => ['sometimes', 'boolean'],
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
            $duplicateFreeUniform = $this->getDuplicateFreeUniformRequest($validated);
            if ($duplicateFreeUniform) {
                return $this->sendError([
                    'message' => 'This employee has already reached the allowed free uniform limit for the current period.',
                    'previous_request_number' => $duplicateFreeUniform->request_number,
                    'previous_request_date' => $duplicateFreeUniform->date_requested,
                ], 422);
            }
            $latestPhysicalCount = UniformPhysicalCount::latest('id')->first();
            if($latestPhysicalCount->status != 'Updated'){
                return $this->sendError([
                    'message' => 'The latest Physical Count was not Updated.Please update the quantity first.',
                    'physical_count_status' => $latestPhysicalCount->status,
                ],422);
            }
            $issuance = DB::transaction(function () use ($request, $validated) {
                $itemNumbers = collect($validated['items'])->pluck('item_no');
                $uniformItems = UniformItem::with(['size', 'gender'])
                    ->whereIn('item_no', $itemNumbers)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('item_no');
                $employeeGender = Employee::where('employee_number', $validated['employee_id'])->value('gender');
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
                    
                ]);
                $grandTotal = $this->replaceRequestItems(
                    $issuance,
                    $validated['items'],
                    $uniformItems,
                    $employeeGender
                );
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
    
    private function replaceRequestItems(RequestAndIssuance $issuance,array $items,$uniformItems,?string $employeeGender): float {
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

   private function getDuplicateFreeUniformRequest(array $validated, ?int $excludeId = null): ?RequestAndIssuance{
        if (($validated['issuance_type'] ?? null) !== 'Free Uniform') {
            return null;
        }
        $allowedCount = 1;

        $limit = UniformFreeUniformLimit::where('status', 'active')
            ->latest('id')
            ->first();

        Log::info('Limit Result', [
            'limit' => $limit,
        ]);

        $employee = Employee::where('employee_number', $validated['employee_id'])
            ->first();

        if (!$employee || !$employee->date_hired) {
            return null;
        }

        $hireDate = \Carbon\Carbon::parse($employee->date_hired);
        $yearsOfService = $hireDate->diffInYears(now());
        if ($limit && today()->gte($limit->date_from)) {
            $allowedCount = $limit->allowed_count;
            if ($yearsOfService === 0) {
                $allowedCount = 3;
            }
        }

        $periodStart = $hireDate->copy()->year(now()->year);

        if ($periodStart->gt(now())) {
            $periodStart->subYear();
        }

        $periodEnd = $periodStart->copy()
            ->addYear()
            ->subDay();

        $count = RequestAndIssuance::where('request_and_issuances.employee_id', $validated['employee_id'])
            ->where('request_and_issuances.status', '<>', 'Cancelled')
            ->where('request_and_issuances.issuance_type', 'Free Uniform')
            ->where('request_and_issuances.request_number', 'LIKE', 'FR-URN%')
            ->when(
                $excludeId,
                fn ($query) => $query->where('request_and_issuances.id', '<>', $excludeId)
            )
            ->whereBetween(
                'request_and_issuances.date_requested',
                [$periodStart, $periodEnd]
            )
            ->join(
                'request_and_issuance_items',
                'request_and_issuances.request_number',
                '=',
                'request_and_issuance_items.requested_number_id'
            )
            ->sum('request_and_issuance_items.requested_quantity');

        $currentRequestQty = collect($validated['items'])
            ->sum('requested_quantity');

        $totalQty = $count + $currentRequestQty;

        Log::info('Free Uniform Limit Check', [
            'employee_id' => $validated['employee_id'],
            'allowed_count' => $allowedCount,
            'already_availed' => $count,
            'current_request' => $currentRequestQty,
            'total_quantity' => $totalQty,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ]);

        if ($totalQty > $allowedCount) {
            return RequestAndIssuance::where('employee_id', $validated['employee_id'])
                ->where('status', '<>', 'Cancelled')
                ->where('issuance_type', 'Free Uniform')
                ->where('request_number', 'LIKE', 'FR-URN%')
                ->latest('id')
                ->first();
        }

        return null;
    }

}
 
