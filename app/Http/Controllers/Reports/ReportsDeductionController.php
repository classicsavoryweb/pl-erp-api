<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\EmployeeDeductionsReportExport;
use App\Http\Controllers\Controller;
use App\Models\Cutoff;
use App\Models\DeductionHistory;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportsDeductionController extends Controller
{
    public function exportDeductionReport(Request $request) {
        try {
            $request->validate([
                "type" => "required"
            ]);

            $cutOff = Cutoff::where("status", "active")->first();
            if ($request->type == "HISTORY") {
                $cutOff = Cutoff::find($request->pay_date);
            }

            $deductions = DeductionHistory::with([
                'employee',
                'actual_deduction',
                'deduction'
            ])->where("pay_date", $cutOff->pay_date)
            ->get();

            return Excel::download(new EmployeeDeductionsReportExport($deductions), "employee_deductions.xlsx", \Maatwebsite\Excel\Excel::XLSX);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
