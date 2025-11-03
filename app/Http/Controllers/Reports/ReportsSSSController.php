<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ReportsSSSContributionsExport;
use App\Exports\Reports\ReportsSSSLoansExport;
use App\Http\Controllers\Controller;
use App\Models\Deduction;
use App\Models\DeductionHistory;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ReportsSSSController extends Controller
{
    public function getAllReportsSSSContributions(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = Payroll::with(['employee', 'employee.current_branch', 'employee.current_position']);

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
                    new ReportsSSSContributionsExport($payrollEmployee, $withDash),
                    "sss_monthly_contribution.xls"
                );
            }

            $groupedPayroll = $query->get()->groupBy('employee_id')->map(function ($employeePayrolls) {
                $employee_sss_first_cutoff = 0;
                $employee_sss_second_cutoff = 0;

                $unique_payrollEmployee = $employeePayrolls->first();

                foreach ($employeePayrolls as $payroll) {
                    $start_date_day = Carbon::parse($payroll->start_date)->day;

                    if ($start_date_day == 26) {
                        $employee_sss_first_cutoff = $payroll->employee_sss;
                    } elseif ($start_date_day == 11) {
                        $employee_sss_second_cutoff = $payroll->employee_sss;
                    }
                }

                return (object) [
                    'employee' => $unique_payrollEmployee->employee,
                    'first_cutoff_employee_contribution' => $employee_sss_first_cutoff,
                    'second_cutoff_employee_contribution' => $employee_sss_second_cutoff,
                    'total_employee_contribution' => $employee_sss_first_cutoff + $employee_sss_second_cutoff,
                ];
            });

            $items = $groupedPayroll->values();
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $paginatedItems = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $paginated = new LengthAwarePaginator(
                $paginatedItems,
                $items->count(),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return $this->sendResponse($paginated);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllReportsSSSContributions(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsSSSContributions($request);
    }

    public function getReportsSSSLoanTypes()
    {
        try {
            $reportsSSS = Deduction::where('category', 'SSS')
                ->distinct('description')
                ->pluck('description');
                
            return $this->sendResponse($reportsSSS);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAllReportsSSSLoans(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = DeductionHistory::whereHas('deduction', function ($q) {
                $q->where('category', 'SSS');
            })->with(['employee', 'deduction', 'employeeDeductions']);

            if ($request->has('year')) {
                $query->whereYear('pay_date', $request->year);
            }

            if ($request->has('month')) {
                $query->whereMonth('pay_date', $request->month);
            }

            if ($request->has('loan_type')) {
                $loanType = $request->loan_type;

                $query->whereHas('deduction', function ($q) use ($loanType) {
                    $q->where('description', $loanType);
                });
            }

            $withDash = $request->with_dash ?? 0;

            if ($request->has('export') && $request->export) { 
                $payrollEmployee = $query->get()->unique('employee_id');
                return Excel::download(new ReportsSSSLoansExport($payrollEmployee, $withDash), "sss_loans.xls"); 
            }

            $paginated = $query->paginate($perPage);

            return $this->sendResponse($paginated);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllReportsSSSLoans(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsSSSLoans($request);
    }
}
