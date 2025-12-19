<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Jobs\SendPayslipsToEmployeeJob;
use App\Models\Cutoff;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Exception;
use DB;

class PayslipController extends Controller
{
    public function submitPayslip (Request $request) {
        try {
            DB::beginTransaction();

            $payroll = Payroll::with(["payroll_list", "employee", "timekeeping_summary"])
            ->whereHas("payroll_list", function ($query) {
                $query->where("is_hold", 0)
                ->where("is_on_maternity_leave", 0);
            });

            if ($request->type === "Branch") {
                if ($request->branch_id !== 0) {
                    $payroll = $payroll->where("timekeeping_branch_id", $request->branch_id);                
                }
            } else {
                $payroll = $payroll->where("employee_id", $request->employee_id);
            }

            if ($request->payslip === "current") {
                $cutOff = Cutoff::where("status", "active")->first();
                $payroll = $payroll->where("pay_date", $cutOff->pay_date);
            } else {
                if ($request->pay_date == 0) {
                    $cutOffFrom = Cutoff::find($request->pay_date_from);
                    $cutOffTo = Cutoff::find($request->pay_date_to);
                    $payroll = $payroll->whereBetween("pay_date", [$cutOffFrom->pay_date, $cutOffTo->pay_date]);
                } else {
                    $cutOff = Cutoff::find($request->pay_date);
                    $payroll = $payroll->where("pay_date", $cutOff->pay_date);
                }
            }

            $payroll = $payroll->get();

            if ($request->send_email) {
                SendPayslipsToEmployeeJob::dispatch($payroll);
            }

            DB::commit();
            return $this->sendResponse([
                "data" => $payroll,
                "message" => "Payslip processing successfully"
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
