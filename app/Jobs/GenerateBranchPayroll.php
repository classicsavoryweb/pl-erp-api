<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Cutoff;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use App\Support\NotifiesJobStatus;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class GenerateBranchPayroll implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotifiesJobStatus;


    protected $branchId;
    protected $userId;
    protected $notificationContext;
    protected $payrollService;

    public function __construct(?int $branchId = null, ?int $userId = null, ?string $notificationContext = null)
    {
        $this->branchId = $branchId;
        $this->userId = $userId;
        $this->notificationContext = $notificationContext;
        $this->payrollService = new PayrollService();
    }

    public function handle(): void
    {
        $branchId = $this->branchId;
        $activeCutOff = Cutoff::where("status", "active")->first();
        $timeKeepingSummaries = TimeKeepingSummary::with(["employee", "employee.current_position"])
        ->where("cut_off", $activeCutOff->start_date."-".$activeCutOff->end_date)
        ->where("branch_id", $branchId)
        ->get();

        foreach ($timeKeepingSummaries as $tkSummary) {
            try {
                $employee = $tkSummary->employee;
                $payrollDetails = PayrollDetail::where("employee_id", $tkSummary->employee_id)
                ->where("pay_date", $activeCutOff->pay_date)
                ->first();
    
                $timeKeepings = TimeKeeping::whereBetween("date", [$activeCutOff->start_date, $activeCutOff->end_date])
                ->where("employee_id", $tkSummary->employee_id)
                ->where(function ($query) {
                    $query->where("tardiness", ">", 0)
                    ->orWhere("undertime", ">", 0);
                })->get();
    
                if (!$payrollDetails) {
                    continue;
                }
    
                $undertimeMinutes = [];
                $tardinessMinutes = [];
    
                foreach ($timeKeepings as $timekeep) {
                    if ($timekeep->tardiness > 0) {
                        $tardinessMinutes[] = [
                            "date" => $timekeep->date,
                            "minutes" => $timekeep->tardiness,
                        ];
                    }
                    if ($timekeep->undertime > 0) {
                        $undertimeMinutes[] = [
                            "date" => $timekeep->date,
                            "minutes" => $timekeep->undertime,
                        ];
                    }
                }
    
                $payrollDetails->update([
                    "regular_days" => $tkSummary->regular_days,
                    "special_leave" => $tkSummary->special_leave,
                    "cancelled_off"=> $tkSummary->cancelled_off,
                    "special_holiday" => $tkSummary->special_holiday,
                    "legal_holiday" => $tkSummary->legal_holiday,
                    "regular_overtime" => $tkSummary->regular_overtime,
                    "night_differential" => $tkSummary->night_differential,
                    "night_differential_overtime" => $tkSummary->night_differential_overtime,
                    "legal_holiday_overtime" => $tkSummary->legal_holiday_overtime,
                    "special_holiday_overtime" => $tkSummary->special_holiday_overtime,
                    "undertime" => $tkSummary->undertime,
                    "tardiness" => $tkSummary->tardiness,
                    "travel_time" => $tkSummary->travel_time,
                    "adjustment_amount" => $tkSummary->adjustment_amount,
                    "undertime_minutes" => $undertimeMinutes,
                    "tardiness_minutes" => $tardinessMinutes,
                    "remarks" => $tkSummary->remarks
                ]);
    
                $payrollComp = $this->payrollService->computePayrollV2($payrollDetails, $activeCutOff);
    
                if ($payrollComp) {
                    $payroll = Payroll::updateOrCreate([
                        "employee_id" => $employee->id,
                        "cut_off" => $activeCutOff->cut_off,
                        "start_date" => $activeCutOff->start_date,
                        "end_date" => $activeCutOff->end_date,
                        
                    ], 
                    array_merge(
                        [
                            "tax_status" => $employee->tax_type,
                            "pay_type" => $employee->current_position->timekeeping_type,
                            "pay_date" => $activeCutOff->pay_date,
                            "daily_rate" => $payrollDetails->daily_rate,
                            "hourly_rate" => $payrollDetails->hourly_rate,
                            "timekeeping_branch_id" => $tkSummary->branch_id,
                            "position_id" => $payrollDetails->position_id,
                            "timekeeping_summary_id" => $tkSummary->id,
                        ],
                        (array) $payrollComp
                    )
                    );
    
                    $payrollDetails->update([
                        "payroll_id" => $payroll->id
                    ]);
                }
            } catch (Exception $e) {
                Log::info("ERROR GENERATION OF PAYROLL: ".$employee->employee_number." ERROR: ".$e->getMessage());
            }
        }

        $this->notifyJobFinished();
    }
}
