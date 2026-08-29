<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashIssuance;
use App\Models\PettyCashReplenishment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;

class CashIssuanceController extends Controller
{
    private function getActiveCustodian()
    {
        return PettyCashCustodian::where('user_id', auth()->id())
            ->where('status', 'active')
            ->with(['branches'])
            ->first();
    }

    // naka-freeze na yung snapshot pag may replenishment today, bawal muna mag-add hanggang bukas
    private function hasBlockingReplenishmentToday($custodian)
    {
        return PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', ['Pending', 'Posted'])
            ->exists();
    }

    public function getAllCashIssuances(Request $request)
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $branchIds = $custodian->branches->pluck('id');
            $perPage = $request->per_page ?? 10;

            $query = PettyCashIssuance::with([
                'branch',
                'requestedBy',
                'receivedBy',
                'approvedBy',
                'createdBy',
            ])->whereIn('branch_id', $branchIds);

            if ($request->filled('pcv_number')) {
                $query->where('pcv_number', 'LIKE', '%' . $request->pcv_number . '%');
            }

            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween(DB::raw('DATE(created_at)'), [$request->date_from, $request->date_to]);
            } elseif ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            } elseif ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->filled('requested_by')) {
                $query->where('requested_by_employee_number', $request->requested_by);
            }

            if ($request->filled('received_by')) {
                $query->where('received_by_employee_number', $request->received_by);
            }

            if ($request->has(['export']) && $request->export) {
                $issuances = $query->orderBy('created_at', 'desc')->get();
                return $this->exportCashIssuances($issuances);
            }

            $issuances = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->sendResponse($issuances);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function exportAllCashIssuances(Request $request)
    {
        $request['export'] = true;
        return $this->getAllCashIssuances($request);
    }

    private function exportCashIssuances($issuances)
    {
        $headers = [
            'PCV Number', 'Transaction Date', 'Branch', 'Requested By',
            'Received By', 'Approved By', 'Cash Issuance Amount', 'Unliquidated Amount',
            'Over/Under Amount', 'Liquidation Date', 'Detailed Purpose', 'Status', 'Void Reason', 'Source', 'Date Created',
        ];

        $rows = $issuances->map(function ($item) {
            return [
                $item->pcv_number . ($item->cshub_trans_no ? " ({$item->cshub_trans_no})" : ''),
                $item->date,
                $item->branch->name ?? '',
                $item->requestedBy ? $item->requestedBy->full_name : '',
                $item->receivedBy ? $item->receivedBy->full_name : '',
                $item->approvedBy ? $item->approvedBy->full_name : '',
                $item->amount,
                $item->unliquidated_amount,
                $item->overunder_amount,
                $item->liquidated_at ? $item->liquidated_at->format('Y-m-d H:i:s') : '',
                $item->detailed_purpose,
                $item->status,
                $item->void_reason ?? '',
                $item->source,
                $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $export = new \App\Exports\PettyCash\CashIssuanceExport($rows->toBase(), $headers);
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'cash-issuances.xls');
    }

    public function getPCVNumber(Request $request)
    {
        try {
            $request->validate(['branch_id' => 'required|exists:branches,id']);

            $branch = Branch::find($request->branch_id);
            $count = PettyCashIssuance::where('branch_id', $request->branch_id)->count() + 1;
            $pcvNumber = strtoupper($branch->short_name) . 'PCV' . str_pad($count, 6, '0', STR_PAD_LEFT);

            return $this->sendResponse(['pcv_number' => $pcvNumber]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getBranchEmployees(Request $request)
    {
        try {
            $request->validate(['branch_id' => 'required|exists:branches,id']);

            $employees = Employee::where('branch_id', $request->branch_id)
                ->where('status', 'active')
                ->orderBy('last_name')
                ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name']);

            return $this->sendResponse(['employees' => $employees]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getMyEmployees()
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'No active custodian found.']);
            }

            $branchIds = $custodian->branches->pluck('id');

            $employees = Employee::whereIn('branch_id', $branchIds)
                ->where('status', 'active')
                ->orderBy('last_name')
                ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name']);

            return $this->sendResponse(['employees' => $employees]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getBranchApprovers(Request $request)
    {
        try {
            $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'amount'    => 'required|numeric|min:0.01',
            ]);

            $branchId = $request->branch_id;
            $amount   = $request->amount;

            // Employees from groups that use a shared amount range
            $sameRange = DB::table('petty_cash_approver_employee as pae')
                ->join('petty_cash_approvers as pa', 'pa.id', '=', 'pae.approver_id')
                ->join('petty_cash_approver_branch as pab', 'pab.approver_id', '=', 'pa.id')
                ->join('employees as e', 'e.id', '=', 'pae.employee_id')
                ->where('pa.status', 'Active')
                ->where('e.status', 'active')
                ->where('pa.use_same_range', true)
                ->where('pab.branch_id', $branchId)
                ->where('pa.min_amount', '<=', $amount)
                ->where('pa.max_amount', '>=', $amount)
                ->select('e.employee_number', 'e.first_name', 'e.middle_name', 'e.last_name');

            // Employees from groups that use per-employee amount ranges
            $perEmployee = DB::table('petty_cash_approver_employee as pae')
                ->join('petty_cash_approvers as pa', 'pa.id', '=', 'pae.approver_id')
                ->join('petty_cash_approver_branch as pab', 'pab.approver_id', '=', 'pa.id')
                ->join('employees as e', 'e.id', '=', 'pae.employee_id')
                ->where('pa.status', 'Active')
                ->where('e.status', 'active')
                ->where('pa.use_same_range', false)
                ->where('pab.branch_id', $branchId)
                ->where('pae.min_amount', '<=', $amount)
                ->where('pae.max_amount', '>=', $amount)
                ->select('e.employee_number', 'e.first_name', 'e.middle_name', 'e.last_name');

            $approvers = $sameRange->union($perEmployee)
                ->orderBy('last_name')
                ->get();

            return $this->sendResponse(['approvers' => $approvers]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getApproverEmployees()
    {
        try {
            $employeeIds = DB::table('petty_cash_approver_employee as pae')
                ->join('petty_cash_approvers as pa', 'pa.id', '=', 'pae.approver_id')
                ->where('pa.status', 'active')
                ->pluck('pae.employee_id');

            $employees = Employee::whereIn('id', $employeeIds)
                ->where('status', 'active')
                ->orderBy('last_name')
                ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name']);

            return $this->sendResponse(['employees' => $employees]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function createCashIssuance(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'branch_id'                    => 'required|exists:branches,id',
                'requested_by_employee_number' => 'required|exists:employees,employee_number',
                'received_by_employee_number'  => 'required|exists:employees,employee_number',
                'approved_by_employee_number'  => 'required|exists:employees,employee_number',
                'amount'                       => 'required|numeric|min:0.01',
                'detailed_purpose'             => 'required|string|max:1000',
            ]);

            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.']);
            }

            if ($this->hasBlockingReplenishmentToday($custodian)) {
                return $this->sendError(['message' => 'A replenishment created today is still Pending or Posted. You can create new cash issuances once it is Replenished, or starting tomorrow.']);
            }

            if ($request->amount > $custodian->remaining_cash_fund) {
                return $this->sendError(['message' => 'Amount exceeds the remaining cash fund of ₱' . number_format($custodian->remaining_cash_fund, 2) . '.']);
            }

            $approvedByEmployee = Employee::where('employee_number', $request->approved_by_employee_number)->first();
            $isEligible = DB::table('petty_cash_approver_employee as pae')
                ->join('petty_cash_approvers as pa', 'pa.id', '=', 'pae.approver_id')
                ->join('petty_cash_approver_branch as pab', 'pab.approver_id', '=', 'pa.id')
                ->where('pa.status', 'Active')
                ->where('pae.employee_id', $approvedByEmployee->id)
                ->where('pab.branch_id', $request->branch_id)
                ->where(function ($q) use ($request) {
                    $q->where(function ($inner) use ($request) {
                        // Shared range
                        $inner->where('pa.use_same_range', true)
                            ->where('pa.min_amount', '<=', $request->amount)
                            ->where('pa.max_amount', '>=', $request->amount);
                    })->orWhere(function ($inner) use ($request) {
                        // Per-employee range
                        $inner->where('pa.use_same_range', false)
                            ->where('pae.min_amount', '<=', $request->amount)
                            ->where('pae.max_amount', '>=', $request->amount);
                    });
                })
                ->exists();

            if (!$isEligible) {
                return $this->sendError(['message' => 'The selected approver is not authorized to approve this amount for the selected branch.']);
            }

            $branch = Branch::find($request->branch_id);
            $count = PettyCashIssuance::where('branch_id', $request->branch_id)->count() + 1;
            $pcvNumber = strtoupper($branch->short_name) . 'PCV' . str_pad($count, 6, '0', STR_PAD_LEFT);

            PettyCashIssuance::create([
                'pcv_number'                   => $pcvNumber,
                'date'                         => now()->toDateString(),
                'branch_id'                    => $request->branch_id,
                'requested_by_employee_number' => $request->requested_by_employee_number,
                'received_by_employee_number'  => $request->received_by_employee_number,
                'approved_by_employee_number'  => $request->approved_by_employee_number,
                'amount'                       => $request->amount,
                'unliquidated_amount'          => $request->amount,
                'detailed_purpose'             => $request->detailed_purpose,
                'status'                       => 'Unliquidated',
                'created_by_id'                => auth()->id(),
            ]);

            $custodian->decrement('remaining_cash_fund', $request->amount);

            DB::commit();
            return $this->sendResponse(['message' => 'Cash issuance created successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function voidCashIssuance(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:1000',
            ]);

            DB::beginTransaction();

            $issuance = PettyCashIssuance::findOrFail($id);

            if ($issuance->status !== 'Unliquidated') {
                return $this->sendError(['message' => 'Only Unliquidated issuances can be voided.']);
            }

            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.']);
            }

            $issuance->update(['status' => 'Voided', 'void_reason' => $request->reason]);
            $custodian->increment('remaining_cash_fund', $issuance->amount);

            DB::commit();

            if ($issuance->cshub_id) {
                try {
                    $this->updateCSHubTransactionStatus($issuance->cshub_id, 'voided', $issuance->requested_by_employee_number);
                } catch (Exception $e) {
                    return $this->sendResponse(['message' => 'Cash issuance voided successfully, but failed to sync status back to CS Hub: ' . $e->getMessage()]);
                }
            }

            return $this->sendResponse(['message' => 'Cash issuance voided successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getCashIssuance($id)
    {
        try {
            $issuance = PettyCashIssuance::with([
                'branch',
                'requestedBy',
                'receivedBy',
                'approvedBy',
                'createdBy',
            ])->findOrFail($id);

            return $this->sendResponse(['issuance' => $issuance]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function fetchCSHubPettyCash($type, $approvedFrom, $approvedTo)
    {
        $baseUrl = env('CS_HUB_URL_LINK');
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');
        $company = env('COMPANY_NAME');

        if (!$baseUrl || !$user || !$password || !$company) {
            throw new Exception('CS Hub integration is not configured properly.');
        }

        $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->get(rtrim($baseUrl, '/') . '/get-petty-cash-transactions', [
                'type'          => $type,
                'approved_from' => $approvedFrom,
                'approved_to'   => $approvedTo,
                'company'       => $company,
            ]);

        if (!$response->successful()) {
            $message = $response->json('data.message') ?: $response->body();
            throw new Exception('Failed to fetch CS Hub transactions: ' . $message);
        }

        return $response->json('data.transactions') ?: [];
    }

    // type=PETTY_CASH lang ginamit dito, wala itong precondition sa cshub side
    private function updateCSHubTransactionStatus($cshubId, $status, $employeeNumber)
    {
        $baseUrl = env('CS_HUB_URL_LINK');
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');
        $company = env('COMPANY_NAME');

        $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post(rtrim($baseUrl, '/') . '/update-petty-cash-transaction-status', [
                'type'               => 'PETTY_CASH',
                'status'             => $status,
                'transaction_number' => $cshubId,
                'employee_number'    => $employeeNumber,
                'company'            => $company,
            ]);

        if (!$response->successful()) {
            $message = $response->json('data.message') ?: $response->body();
            throw new Exception($message);
        }
    }

    // ito yung shared resolver, ginagamit ng list at import para pareho lang ang lohika
    private function resolveCSHubTransaction($t, $custodian)
    {
        $branchName = trim(isset($t['branch']) ? $t['branch'] : '');
        $branch = $branchName !== ''
            ? Branch::whereRaw('LOWER(name) = ?', [strtolower($branchName)])->first()
            : null;

        $custodianBranchIds = $custodian->branches->pluck('id')->toArray();
        $inScope = $branch && in_array($branch->id, $custodianBranchIds);

        $employeeNumber = isset($t['user']['employee_number']) ? $t['user']['employee_number'] : null;
        $employee = $employeeNumber ? Employee::where('employee_number', $employeeNumber)->first() : null;

        // stored lang muna, di pa enforced yung approver eligibility dito
        $approverEmployeeNumber = isset($t['approvers']['employee_number']) ? $t['approvers']['employee_number'] : null;
        $approver = $approverEmployeeNumber ? Employee::where('employee_number', $approverEmployeeNumber)->first() : null;

        $transNo = isset($t['trans_no']) ? $t['trans_no'] : null;
        $alreadyImported = $transNo && PettyCashIssuance::where('cshub_trans_no', $transNo)->exists();

        $amount = isset($t['amount']) ? (float) $t['amount'] : 0;

        $reasons = [];
        if (!$branch) {
            $reasons[] = 'Branch "' . $branchName . '" is not recognized in ERP.';
        } elseif (!$inScope) {
            $reasons[] = 'Branch "' . $branch->name . '" is not under your custodianship.';
        }
        if (!$employee) {
            $reasons[] = 'Requestor employee not found in ERP.';
        }
        if ($alreadyImported) {
            $reasons[] = 'Already imported.';
        }

        // 0 or negative amount, di na lang ipapakita sa selection
        $importable = empty($reasons) && $amount > 0;

        return [
            'branch'     => $branch,
            'in_scope'   => $inScope,
            'employee'   => $employee,
            'approver'   => $approver,
            'importable' => $importable,
            'reasons'    => $reasons,
        ];
    }

    public function getCSHubImportCandidates(Request $request)
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            if ($this->hasBlockingReplenishmentToday($custodian)) {
                return $this->sendError(['message' => 'A replenishment created today is still Pending or Posted. You can import from CS Hub once it is Replenished or Voided, or starting tomorrow.']);
            }

            $approvedFrom = $request->approved_from ?: now()->subMonths(3)->startOfMonth()->toDateString();
            $approvedTo = $request->approved_to ?: now()->toDateString();

            $transactions = $this->fetchCSHubPettyCash('PETTY_CASH', $approvedFrom, $approvedTo);

            $candidates = [];
            foreach ($transactions as $t) {
                $resolved = $this->resolveCSHubTransaction($t, $custodian);

                if (!$resolved['in_scope']) {
                    continue;
                }

                $candidates[] = [
                    'cshub_id'                      => isset($t['id']) ? $t['id'] : null,
                    'trans_no'                       => isset($t['trans_no']) ? $t['trans_no'] : null,
                    'date'                            => isset($t['expense_date']) ? $t['expense_date'] : (isset($t['date_filed']) ? $t['date_filed'] : null),
                    'branch_id'                       => $resolved['branch']->id,
                    'branch_name'                     => $resolved['branch']->name,
                    'requested_by_employee_number'    => $resolved['employee'] ? $resolved['employee']->employee_number : null,
                    'requested_by_name'                => $resolved['employee']
                        ? "{$resolved['employee']->last_name}, {$resolved['employee']->first_name}"
                        : (isset($t['user']['name']) ? $t['user']['name'] : '—'),
                    'approved_by_employee_number'    => $resolved['approver'] ? $resolved['approver']->employee_number : null,
                    'approved_by_name'                => $resolved['approver']
                        ? "{$resolved['approver']->last_name}, {$resolved['approver']->first_name}"
                        : (isset($t['approvers']['full_name']) ? $t['approvers']['full_name'] : '—'),
                    'amount'                          => isset($t['amount']) ? $t['amount'] : 0,
                    'purpose'                         => isset($t['purpose']) ? $t['purpose'] : '',
                    'importable'                      => $resolved['importable'],
                    'reasons'                         => $resolved['reasons'],
                ];
            }

            return $this->sendResponse([
                'transactions'         => $candidates,
                'remaining_cash_fund'  => $custodian->remaining_cash_fund,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function importCSHubPettyCash(Request $request)
    {
        $request->validate([
            'cshub_ids'   => 'required|array|min:1',
            'cshub_ids.*' => 'required',
        ]);

        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            if ($this->hasBlockingReplenishmentToday($custodian)) {
                return $this->sendError(['message' => 'A replenishment created today is still Pending or Posted. You can import from CS Hub once it is Replenished or Voided, or starting tomorrow.']);
            }

            $approvedFrom = $request->approved_from ?: now()->subMonths(3)->startOfMonth()->toDateString();
            $approvedTo = $request->approved_to ?: now()->toDateString();

            $transactions = $this->fetchCSHubPettyCash('PETTY_CASH', $approvedFrom, $approvedTo);
            $byId = [];
            foreach ($transactions as $t) {
                if (isset($t['id'])) {
                    $byId[$t['id']] = $t;
                }
            }

            $results = [];

            // i-total muna lahat bago mag-create ng kahit isa
            $importableRows = [];
            $totalAmount = 0;

            foreach ($request->cshub_ids as $cshubId) {
                $t = isset($byId[$cshubId]) ? $byId[$cshubId] : null;

                if (!$t) {
                    $results[] = [
                        'cshub_id' => $cshubId,
                        'success'  => false,
                        'message'  => 'Transaction is no longer available for import on CS Hub.',
                    ];
                    continue;
                }

                $resolved = $this->resolveCSHubTransaction($t, $custodian);
                if (!$resolved['importable']) {
                    $results[] = [
                        'cshub_id' => $cshubId,
                        'success'  => false,
                        'message'  => implode(' ', $resolved['reasons']),
                    ];
                    continue;
                }

                $amount = isset($t['amount']) ? $t['amount'] : 0;
                $totalAmount += $amount;
                $importableRows[] = ['cshub_id' => $cshubId, 't' => $t, 'resolved' => $resolved, 'amount' => $amount];
            }

            // kapag sobra sa fund yung total, wala ni isang ma-iimport, manual na lang pipili ulit
            if ($importableRows && $totalAmount > $custodian->remaining_cash_fund) {
                return $this->sendError([
                    'message' => 'Total amount of selected transactions (₱' . number_format($totalAmount, 2)
                        . ') exceeds your remaining cash fund of ₱' . number_format($custodian->remaining_cash_fund, 2)
                        . '. Please deselect some transactions.',
                ]);
            }

            foreach ($importableRows as $row) {
                $cshubId = $row['cshub_id'];
                $t = $row['t'];
                $branch = $row['resolved']['branch'];
                $employee = $row['resolved']['employee'];
                $approver = $row['resolved']['approver'];
                $amount = $row['amount'];

                DB::beginTransaction();
                try {
                    $count = PettyCashIssuance::where('branch_id', $branch->id)->count() + 1;
                    $pcvNumber = strtoupper($branch->short_name) . 'PCV' . str_pad($count, 6, '0', STR_PAD_LEFT);

                    PettyCashIssuance::create([
                        'pcv_number'                   => $pcvNumber,
                        'date'                         => isset($t['expense_date']) ? $t['expense_date'] : now()->toDateString(),
                        'branch_id'                    => $branch->id,
                        'requested_by_employee_number' => $employee->employee_number,
                        'received_by_employee_number'  => $employee->employee_number,
                        'approved_by_employee_number'  => $approver ? $approver->employee_number : null,
                        'amount'                       => $amount,
                        'unliquidated_amount'          => $amount,
                        'detailed_purpose'              => isset($t['purpose']) ? $t['purpose'] : '',
                        'status'                        => 'Unliquidated',
                        'cshub_trans_no'                => isset($t['trans_no']) ? $t['trans_no'] : null,
                        'cshub_id'                      => $t['id'],
                        'source'                        => 'CSHUB',
                        'created_by_id'                 => auth()->id(),
                    ]);

                    $custodian->decrement('remaining_cash_fund', $amount);

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $results[] = [
                        'cshub_id' => $cshubId,
                        'success'  => false,
                        'message'  => 'Failed to create cash issuance: ' . $e->getMessage(),
                    ];
                    continue;
                }

                // committed na ito sa erp, yung unique trans_no na lang bantay sa duplicate
                try {
                    $this->updateCSHubTransactionStatus($t['id'], 'unliquidated', $employee->employee_number);
                    $results[] = ['cshub_id' => $cshubId, 'success' => true, 'message' => 'Imported successfully.'];
                } catch (Exception $e) {
                    $results[] = [
                        'cshub_id' => $cshubId,
                        'success'  => true,
                        'message'  => 'Imported to ERP, but failed to sync status back to CS Hub: ' . $e->getMessage(),
                    ];
                }
            }

            return $this->sendResponse(['results' => $results]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
