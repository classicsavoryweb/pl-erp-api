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
use App\Http\Services\UniformRequestItemService;
use App\Http\Services\FreeUniformLimitService;

class RequestAndIssuanceController extends Controller
{
    public function __construct(
        private UniformRequestItemService $uniformRequestItemService,
        private FreeUniformLimitService $freeUniformLimitService
    ) {}
  public function getUniformRequestAndIssuance(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $issuance = RequestAndIssuance::with('items','branches')
                ->orderBy("status","desc")
                ->latest("id");
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

            $duplicateFreeUniform = $this->freeUniformLimitService->check($validated, (int) $id);

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
                
                $grandTotal = $this->uniformRequestItemService->replaceItems(
                    $issuance,
                    $validated['items'],
                    $uniformItems,
                    
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
            $duplicate = $this->freeUniformLimitService->check($validated);

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'issuance_type' =>
                        'This employee has already reached the allowed free uniform limit.'
                ]);
            }

            $issuance = DB::transaction(function () use ($request,$validated) {

                $itemNumbers = collect($validated['items'])
                    ->pluck('item_no');

                $uniformItems = UniformItem::with([
                    'size',
                    'gender'
                ])
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
                    'created_by' =>
                        optional($request->user())->proper_full_name,
                ]);

                $grandTotal =$this->uniformRequestItemService->replaceItems(
                    $issuance,
                    $validated['items'],
                    $uniformItems
                );

                $issuance->update([
                    'total_amount' => $grandTotal
                ]);

                return $issuance->fresh('items');
            });

            return $this->sendResponse([
                'message' => 'Uniform request created successfully.',
                'record' => $issuance,
            ]);

        } catch (ValidationException $e) {

            return $this->sendError([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } 
    }

}
 
