<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\TimeKeepingSummaryReportExport;
use App\Http\Controllers\Controller;
use App\Models\Cutoff;
use App\Models\PayrollList;
use DB;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TimeKeepingSummaryReportController extends Controller
{
    public function exportTimeKeepingSummaryReport(Request $request) {
        try {
            $request->validate([
                "type" => "required"
            ]);

            $cutOff = Cutoff::where("status", "active")->first();
            if ($request->type == "HISTORY") {
                $cutOff = Cutoff::find($request->pay_date);
            }

            $timeKeepingSummaries = PayrollList::select(
                "payroll_list.*",
                "employees.first_name",
                "employees.last_name",
                "employees.middle_name",
                "employees.employee_number",
                "employees.status as employee_status",
                "timekeeping_summary.regular_days",
                "cb.name as current_branch_name",
                "cb.short_name as current_branch_code",
                "tk.name as timekeeping_branch_name",
                "tk.short_name as timekeeping_branch_code",
            )
            ->leftJoin("timekeeping_summary", function ($query) use ($cutOff) {
                $query->on("timekeeping_summary.employee_id", "=", "payroll_list.employee_id")
                ->where("timekeeping_summary.cut_off", $cutOff->cut_off);
            })
            ->leftJoin("employees", "employees.id", "=" ,"payroll_list.employee_id")
            ->leftJoin("branches as cb", "cb.id", "=", "employees.branch_id")
            ->leftJoin("branches as tk", "tk.id", "=", "timekeeping_summary.branch_id")
            ->where("payroll_list.is_included", 1)
            ->get();

            return Excel::download(new TimeKeepingSummaryReportExport($timeKeepingSummaries), "timekeeping_summary.xlsx", \Maatwebsite\Excel\Excel::XLSX);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
