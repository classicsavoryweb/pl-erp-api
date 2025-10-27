<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ReportsphilhealthContributionsExport;
use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ReportsPhilhealthController extends Controller
{
    public function getAllReportsPhilhealthContributions(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = Payroll::with(['employee', 'employee.employee_status']);

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
                    new ReportsPhilhealthContributionsExport($payrollEmployee, $withDash),
                    "philhealth.xls"
                );
            }

            $groupedPayroll = $query->get()->groupBy('employee_id')->map(function ($employeePayrolls) {
                $employee_philhealth_first_cutoff = 0;
                $employee_philhealth_second_cutoff = 0;

                $unique_payrollEmployee = $employeePayrolls->first();

                foreach ($employeePayrolls as $payroll) {
                    $start_date_day = Carbon::parse($payroll->start_date)->day;

                    if ($start_date_day == 26) {
                        $employee_philhealth_first_cutoff = $payroll->employee_philhealth;
                    } elseif ($start_date_day == 11) {
                        $employee_philhealth_second_cutoff = $payroll->employee_philhealth;
                    }
                }

                return (object) [
                    'employee_status' => $unique_payrollEmployee->employee->employee_status,
                    'employee' => $unique_payrollEmployee->employee,
                    'first_cutoff_employee_contribution' => $employee_philhealth_first_cutoff,
                    'second_cutoff_employee_contribution' => $employee_philhealth_second_cutoff,
                    'total_employee_contribution' => $employee_philhealth_first_cutoff + $employee_philhealth_second_cutoff,
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

    public function exportAllReportsPhilhealthContributions(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsPhilhealthContributions($request);
    }
}
