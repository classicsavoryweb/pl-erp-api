<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashIssuance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'PCV Number', 'Date', 'Branch', 'Requested By',
            'Received By', 'Amount', 'Detailed Purpose', 'Status', 'Date Created',
        ];

        $rows = $issuances->map(function ($item) {
            return [
                $item->pcv_number,
                $item->date,
                $item->branch->name ?? '',
                $item->requestedBy ? $item->requestedBy->full_name : '',
                $item->receivedBy ? $item->receivedBy->full_name : '',
                $item->amount,
                $item->detailed_purpose,
                $item->status,
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

    public function voidCashIssuance($id)
    {
        try {
            DB::beginTransaction();

            $issuance = PettyCashIssuance::findOrFail($id);

            if ($issuance->status !== 'Unliquidated') {
                return $this->sendError(['message' => 'Only Unliquidated issuances can be voided.']);
            }

            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.']);
            }

            $issuance->update(['status' => 'Voided']);
            $custodian->increment('remaining_cash_fund', $issuance->amount);

            DB::commit();
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
                'createdBy',
            ])->findOrFail($id);

            return $this->sendResponse(['issuance' => $issuance]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
