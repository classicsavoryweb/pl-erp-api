<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Jobs\SendPayslipsToEmployeeJob;
use App\Models\Cutoff;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Exception;
use DB;
use Illuminate\Support\Facades\Auth;

class PayslipController extends Controller
{
    private function buildPayrollQuery(Request $request)
    {
        $payroll = Payroll::with([
            "payroll_list",
            "employee.current_position",
            "employee.current_branch",
            "timekeeping_summary",
            "timekeeping_branch",
        ])->whereHas("payroll_list", function ($query) {
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
            if (!$cutOff) {
                throw new Exception("No active cutoff found.");
            }
            $payroll = $payroll->where("pay_date", $cutOff->pay_date);
        } else {
            if ($request->pay_date == 0) {
                $cutOffFrom = Cutoff::find($request->pay_date_from);
                $cutOffTo = Cutoff::find($request->pay_date_to);
                if (!$cutOffFrom || !$cutOffTo) {
                    throw new Exception("Invalid pay date range.");
                }
                $payroll = $payroll->whereBetween("pay_date", [$cutOffFrom->pay_date, $cutOffTo->pay_date]);
            } else {
                $cutOff = Cutoff::find($request->pay_date);
                if (!$cutOff) {
                    throw new Exception("Invalid pay date.");
                }
                $payroll = $payroll->where("pay_date", $cutOff->pay_date);
            }
        }

        return $payroll;
    }

    public function submitPayslip (Request $request) {
        try {
            DB::beginTransaction();

            if ($request->payslip === "current") {
                $activeCutOff = Cutoff::where("status", "active")->first();

                if ($activeCutOff && $activeCutOff->is_cutoff_locked == 0) {
                    return $this->sendError(["message" => "Cutoff not finalized"]);
                }
            }

            $payroll = $this->buildPayrollQuery($request)->get();

            if ($request->send_email) {
                SendPayslipsToEmployeeJob::dispatch($payroll, Auth::id());
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

    public function exportPayslip(Request $request)
    {
        try {
            $payroll = $this->buildPayrollQuery($request)->get();

            return $this->sendResponse([
                "data" => $payroll
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
