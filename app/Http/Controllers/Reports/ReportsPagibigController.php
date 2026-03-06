<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ReportsPagibigContributionsExport;
use App\Exports\Reports\ReportsPagibigLoansExport;
use App\Http\Controllers\Controller;
use App\Models\Deduction;
use App\Models\DeductionHistory;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

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
                    "pagibig_monthly_contribution.xlsx"
                );
            }

            $groupedPayroll = $query->get()->groupBy('employee_id')->map(function ($employeePayrolls) {
                $employee_pagibig_first_cutoff = 0;
                $employee_pagibig_second_cutoff = 0;

                $unique_payrollEmployee = $employeePayrolls->first();

                foreach ($employeePayrolls as $payroll) {
                    $start_date_day = Carbon::parse($payroll->start_date)->day;

                    if ($start_date_day == 26) {
                        $employee_pagibig_first_cutoff = $payroll->employee_pagibig;
                    } elseif ($start_date_day == 11) {
                        $employee_pagibig_second_cutoff = $payroll->employee_pagibig;
                    }
                }

                return (object) [
                    'employee' => $unique_payrollEmployee->employee,
                    'first_cutoff_employee_contribution' => $employee_pagibig_first_cutoff,
                    'second_cutoff_employee_contribution' => $employee_pagibig_second_cutoff,
                    'total_employee_contribution' => $employee_pagibig_first_cutoff + $employee_pagibig_second_cutoff,
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

    public function exportAllReportsPagibigContributions(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsPagibigContributions($request);
    }

    public function getReportsPagibigLoanTypes()
    {
        try {
            $reportsPagibig = Deduction::where('category', 'Pag-ibig')
                ->distinct('description')
                ->pluck('description');
                
            return $this->sendResponse($reportsPagibig);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
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
                } else if ($loanType === "Short Term") {
                    $query->whereDoesntHave('employeeDeductions', function($q) {
                        $q->where('remarks', 'like', 'Calamity%');
                    });
                } else {
                    $query->whereHas('deduction', function ($q) use ($loanType) {
                        $q->where('description', $loanType);
                    });
                }
            }

            $withDash = $request->with_dash ?? 0;

            if ($request->has('export') && $request->export) { 
                $payrollEmployee = $query->get()->unique('employee_id');
                return Excel::download(new ReportsPagibigLoansExport($payrollEmployee, $withDash), "pagibig_loans.xlsx"); 
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
