<?php

namespace App\Jobs;

use App\Models\Cutoff;
use App\Models\IncentivePosition;
use App\Models\Incentives;
use App\Models\IncentivesBracket;
use App\Models\IncentivesManagement;
use App\Models\IncentivesPerEmployee;
use App\Models\Tardiness;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\NotifiesJobStatus;
use Exception;
use Log;

class RecomputeIncentives implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotifiesJobStatus;
    protected Cutoff $cutOff;

    protected $userId;
    /**
     * Create a new job instance.
     */
    public function __construct(Cutoff $cutOff, $userId)
    {
        $this->cutOff = $cutOff;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutOff = $this->cutOff;
        try {
            $incentives = Incentives::with(["employee", "position"])
            ->whereIn("pay_date", [$cutOff->end_date, $cutOff->pay_date])
            ->get();

            foreach ($incentives as $incentive) {
                $position = $incentive->position;
                $employee = $incentive->employee;

                if ($position->rank == 1) {
                    $months = Carbon::now()->diffInMonths($employee->date_hired);
                    $bracket = IncentivesBracket::where("month", "<=", $months)
                        ->whereHas('positions', function ($query) use ($employee) {
                            $query->where('positions.id', $employee->position_id);
                        })
                        ->orderByRaw('CAST(month AS UNSIGNED) DESC')
                        ->first();

                    if ($bracket) {
                        $incentivesAmount = $bracket->amount;
                    }
                    
                    $rankAndFileIncentive = IncentivesPerEmployee::where("employee_id", $employee->id)->first();

                    if ($rankAndFileIncentive) {
                        $incentivesAmount = $rankAndFileIncentive->max_amount;
                    }
                } else {
                    $managementIncentives = IncentivesManagement::where("employee_id", $employee->id)->first();

                    if ($managementIncentives) {
                        $incentivesAmount = $managementIncentives->max_amount;
                    }
                }

                $employeeId = $employee->id;
                $positionId = $position->id;
                $incentiveAmount = 0;
                $maxAmount = $incentivesAmount;
                $payDate = $cutOff->pay_date;
                $amountRate = $incentivesAmount;
                $additionalIncentive = 0;
                $adjustmentPercent = 0;
                $status = "initial";

                $monthWorkedDays = $incentive->first_cut_off_worked_days + $incentive->second_cut_off_worked_days;
                $monthShouldBeWorkDays = 26;
                $dailyIncentives = $incentivesAmount / 26;
                if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
                    $dailyIncentives = $incentivesAmount / 22; 
                    $monthShouldBeWorkDays = 22;
                }

                $incentivePosition = IncentivePosition::where("position_id", $positionId)->first();
                if ($employee->oic_status == "OIC" && $incentivePosition) {
                    $baseAdditional = $incentivePosition->oic_additional_amount;
                
                    $additionalDailyRate = $baseAdditional / $monthShouldBeWorkDays;

                    $additionalIncentive = $additionalDailyRate * ($monthShouldBeWorkDays - $incentive->absent);
                }

                if ($employee->current_position->timekeeping_type == "Daily") {
                    if ($monthWorkedDays > $monthShouldBeWorkDays) {
                        $incentiveAmount = $amountRate;
                    } else {
                        $incentiveAmount = $dailyIncentives * $monthWorkedDays;
                    }
                } else {
                    $incentiveAmount = $dailyIncentives * $monthWorkedDays;
                }

                $tardinessDateBasis = Carbon::parse($cutOff->end_date)->subMonths(2);

                $tardiness = Tardiness::with(["tardiness_sanction"])
                ->where("year", $tardinessDateBasis->year)
                ->where("month", $tardinessDateBasis->month)
                ->where("employee_id", $employee->id)
                ->where("status", "Approved")
                ->first();

                if ($tardiness) {
                    $percentage = $tardiness->tardiness_sanction->incentives_deduction;
                    $adjustmentPercent = $percentage;

                    if ($percentage > 0) {
                        $deduction = $maxAmount * ($percentage * 0.01);

                        if ($deduction >= $incentiveAmount) {
                            $incentiveAmount = 0;
                        } else {
                            $incentiveAmount -= $deduction;
                        }

                        $tardiness->deduction_status = 1;
                        $tardiness->save();
                    }
                }

            // $undertimeDeductionDays =
            // $incentive->adjustment_days -
            // ($monthShouldBeWorkDays - ($incentive->first_cut_off_worked_days + $incentive->second_cut_off_worked_days));

            // if ($undertimeDeductionDays > 0) {
            //     $incentiveAmount -= ($undertimeDeductionDays * $dailyIncentives);
            // }

                if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
                    $allowedUndertimePerMonth = 900; 
                    $basisForDeduction = 240; 

                    $excessiveUndertime = $incentive->undertime - $allowedUndertimePerMonth;

                    if ($excessiveUndertime > 0) {
                        $numberToDeduct = floor($excessiveUndertime / $basisForDeduction);

                        if ($numberToDeduct > 0) {
                            $amountToBeDeduct = $numberToDeduct * 0.5;
                            $deductionAmount = ($amountRate / $monthShouldBeWorkDays) * $amountToBeDeduct;

                            $incentiveAmount -= $deductionAmount;
                        }
                    }       
                } else {
                    if ($incentive->undertime >= 240) {
                        $basisForDeduction = 240;

                        $numberToDeduct = floor($incentive->undertime / $basisForDeduction);

                        if ($numberToDeduct > 0) {
                            $amountToBeDeduct = $numberToDeduct * 0.5;
                            $deductionAmount = ($amountRate / $monthShouldBeWorkDays) * $amountToBeDeduct;

                            $incentiveAmount -= $deductionAmount;
                        }
                    }
                }

                $incentiveAmount += $additionalIncentive;
                $incentive->update([
                    "employee_id" => $employeeId,
                    "position_id" => $positionId,
                    "pay_date" => $payDate,
                    "incentive_amount" => 0 > $incentiveAmount ? 0 : $incentiveAmount,
                    "max_amount" => $maxAmount,
                    "amount_rate" => $amountRate,
                    "additional_incentive" => $additionalIncentive,
                    "adjustment_percent" => $adjustmentPercent,
                    "status" => $status
                ]);
            }

            $this->notifyJobFinished();
        } catch (Exception $e) {
            Log::info($e);
            $this->notifyJobFailed();
        }
    }
}
