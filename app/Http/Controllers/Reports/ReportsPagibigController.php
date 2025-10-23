<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ReportsPagibigContributionsExport;
use App\Exports\Reports\ReportsPagibigLoansExport;
use App\Http\Controllers\Controller;
use App\Models\DeductionHistory;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class ReportsPagibigController extends Controller
{
    public function getAllReportsPagibigContributions(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = Payroll::with(['employee']);

            if ($request->has('year')) {
                $query->whereYear('pay_date', $request->year);
            }

            if ($request->has('month')) {
                $query->whereMonth('pay_date', $request->month);
            }

            $withDash = $request->with_dash ?? 0;

            if ($request->has('export') && $request->export) {
                $payrollEmployee = $query->get();
                return Excel::download(
                    new ReportsPagibigContributionsExport($payrollEmployee, $withDash),
                    "pagibig_monthly_contribution.xls"
                );
            }

            $paginated = $query->paginate($perPage);
            return $this->sendResponse($paginated);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllReportsPagibigContributions(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsPagibigContributions($request);
    }

    public function getAllReportsPagibigLoans(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = DeductionHistory::whereHas('deduction', function ($q) {
                $q->where('category', 'Pag-ibig');
            })->with(['employee', 'deduction', 'employeeDeductions']);

            if ($request->has('year')) {
                $query->whereYear('pay_date', $request->year);
            }

            if ($request->has('month')) {
                $query->whereMonth('pay_date', $request->month);
            }

            if ($request->has('loan_type')) {
                $loanType = $request->loan_type;

                if ($loanType === "Calamity") {
                    $query->whereHas('employeeDeductions', function($q) {
                        $q->where('remarks', 'like', 'Calamity%');
                    });
                } else {
                    $query->whereDoesntHave('employeeDeductions', function($q) {
                        $q->where('remarks', 'like', 'Calamity%');
                    });
                }
            }

            $withDash = $request->with_dash ?? 0;

            if ($request->has('export') && $request->export) { 
                $payrollEmployee = $query->get();
                return Excel::download( new ReportsPagibigLoansExport($payrollEmployee, $withDash), 
                "pagibig_loans.xls" ); 
            }

            $paginated = $query->paginate($perPage);

            return $this->sendResponse($paginated);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllReportsPagibigLoans(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsPagibigLoans($request);
    }
}
