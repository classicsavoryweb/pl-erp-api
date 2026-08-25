<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ReportIncentiveAttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Incentives;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class ReportsIncentiveAttendanceController extends Controller
{
    public function exportIncentiveAttendance (Request $request) {
        try {
            $request->validate([
                "month" => "required",
                "year" => "required"
            ]);

            $incentives = Incentives::with([
                "employee",
                "employee.current_position",
                "employee.schedule.schedule_type",
                "branch"
            ])
            ->whereMonth("pay_date", $request->month)
            ->where("year", $request->year)
            ->get();

            return Excel::download(new ReportIncentiveAttendanceExport($incentives), "incentive_attendance.xlsx", \Maatwebsite\Excel\Excel::XLSX);

        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
