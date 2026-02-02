<?php

namespace App\Jobs;

use App\Http\Services\TimeKeepingService;
use App\Models\Adjustment;
use App\Models\AdjustmentPunch;
use App\Models\AdjustmentTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LateTimeKeepingSummary;
use App\Models\Overtime;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingPayment;
use App\Models\TimeKeepingSummary;
use Auth;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use App\Support\NotifiesJobStatus;
use Log;
class GenerateTimekeepingSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;
    protected $branchId;
    protected $currentCutOff;
    protected $timeKeepingService;
    protected $userId;
    protected $employeeId = 0;
    protected $notificationContext;
    protected $suppressNotification = false;
    /**
     * Create a new job instance.
     */
    public function __construct(
        $branchId,
        $currentCutOff,
        ?int $userId = null,
        int $employeeId = 0,
        ?string $notificationContext = null,
        bool $suppressNotification = false
    )
    {
        $this->branchId = $branchId;
        $this->currentCutOff = $currentCutOff;
        $this->timeKeepingService = new TimeKeepingService();
        $this->userId = $userId;
        $this->employeeId = $employeeId;
        $this->notificationContext = $notificationContext;
        $this->suppressNotification = $suppressNotification;
    }

    public function handle(): void
    {
        try {
            $cutOff = $this->currentCutOff;
            $branchId = $this->branchId;
            $employeeId = $this->employeeId;

            $employees = Employee::with(["schedule", "schedule.schedule_type"])
            ->whereHas("timekeeping", function ($query) use ($branchId) {
                $query->where("branch_id", $branchId);
            })
            ->where("status", "active")
            ->where("type", "Direct");


            if ($employeeId !== 0) {
                $employees = $employees->where("id", $employeeId);
            }

            $employees = $employees->get();

            $activeCutOff = Cutoff::where("status", "active")->first();

            $start_date = Carbon::parse($activeCutOff->start_date)->subMonth()->day(26)->format("Y-m-d");
            $end_date = Carbon::parse($start_date)->addMonth()->day(10)->format("Y-m-d");

            if ($activeCutOff->part == 1) {
                $start_date = Carbon::parse($activeCutOff->start_date)->day(11)->format("Y-m-d");
                $end_date = Carbon::parse($activeCutOff->start_date)->day(25)->format("Y-m-d");
            }

            foreach ($employees as $employee) {
            $adjustmentOvertimes = Overtime::where("employee_id", $employee->id)
            ->whereNotBetween("overtime_date", $cutOff)
            ->whereBetween("date_filed", [$start_date, $end_date])
            ->get();

            foreach ($adjustmentOvertimes as $overtime) {

                $timeKeeping = TimeKeeping::with(["schedule"])
                ->where("employee_id", $employee->id)
                ->where("date", $overtime->overtime_date)
                ->first();

                $isExisting = Adjustment::where("type","Overtime Force Adjustments")
                ->where("employee_id", $employee->id)
                ->where("date", $overtime->overtime_date)
                ->first();
                
                if ($timeKeeping) {
                    $schedule = $timeKeeping->schedule;
                    $oldtimeKeepingPayment = TimeKeepingPayment::where("timekeeping_id", $timeKeeping->id)->first();
                    $log = BiotimeLog::where("employee_id", $employee->id)
                    ->where("date", $timeKeeping->date)
                    ->first();

                    if (
                        !$schedule || 
                        !property_exists($schedule, 'timetable') ||
                        !$schedule->timetable
                    ) {
                        continue;
                    }

                    if ($isExisting) {
                        $isExisting->delete();
                    }

                    $adjustedTimeKeeping = $this->timeKeepingService->computeTimeKeeping(
                        $timeKeeping->branch_id,
                        $log,
                        $log->punches,
                        $schedule->timetable,
                        $employee,
                        new AdjustmentTimeKeeping(),
                        true,
                        "force adjustment"
                    );

                    $adjustedTimeKeepingPayment = $this->timeKeepingService->computePayment(
                        $adjustedTimeKeeping,
                        $schedule->timetable,
                        true
                    );

                    $taxable_amount = $adjustedTimeKeepingPayment->total_amount - ($oldtimeKeepingPayment->total_amount ?? 0);
                    $amount_paid = $oldtimeKeepingPayment->total_amount ?? 0;
                    $adjustment = Adjustment::create([
                        "employee_id" => $employee->id,
                        "biometric_number" => $employee->biometric_number,
                        "date" => $timeKeeping->date,
                        "days" => 0,
                        "cancelled_rd" => 0,
                        "taxable_amount" => round($taxable_amount, 2) ,
                        "amount_paid" => round($amount_paid, 2),
                        "correct_amount_paid" => round($adjustedTimeKeepingPayment->total_amount, 2),
                        "actual_schedule" => $schedule->timetable,
                        "type" => "Overtime Force Adjustments",
                        "status" => "Approved",
                        "added_by" => $this->userId,
                        "added_at" => now(),
                    ]);

                    foreach($log->punches as $punch) {
                        AdjustmentPunch::create([
                            "adjustment_id" => $adjustment->id,
                            "punch_time" => $punch->punch_time,
                            "date" => $timeKeeping->date,
                            "branch_id" => $punch->branch_id ?? null
                        ]);
                    }
                }
            }

            $timeKeepings = TimeKeeping::where("employee_id", $employee->id)
            ->whereBetween("date", $cutOff)
            ->get();

            $regular_days = 0;
            $special_leave= 0;
            $cancelled_off= 0;
            $special_holiday= 0;
            $legal_holiday= 0;
            $regular_overtime= 0;
            $night_differential= 0;
            $night_differential_overtime= 0;
            $legal_holiday_overtime= 0;
            $special_holiday_overtime= 0;
            $undertime= 0;
            $adjustment_amount = 0;
            $travel_time= 0;
            $tardiness = 0;
            $lateDate = [];
            $halfDayCount = 0;
            $daily_rate = round($employee->dailySalary(), 2);
            $hourly_rate = $employee->schedule->schedule_type->name === "Compressed Schedule"
                ? round($daily_rate / 9.5, 2)
                : round($daily_rate / 8, 2);
            $minute_rate = $hourly_rate / 60;

            if ($timeKeepings->count() > 0) {
                foreach ($timeKeepings as $timeKeep) {
                    $actualSchedule = ScheduleTime::find($timeKeep->schedule_time_id);
                    $hours = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;
                    $schedule = ScheduleMasterlist::where("selection", $actualSchedule->timetable)->first();

                    if (!$schedule) {
                        $schedule = ScheduleMasterlist::where("start_time", $actualSchedule->check_in)
                        ->where("end_time", $actualSchedule->check_out)
                        ->first();
                    }

                    $holidayIsPaid = false;
                    $holidayConvertedToDays = false;

                    $holiday = Holiday::where("date", $actualSchedule->date)
                    ->whereHas("branches", function ($query) use($actualSchedule) {
                        return $query->where("branch_id", $actualSchedule->branch_id);
                    })
                    ->first();

                    if ($holiday) {
                        $holidayIsPaid = $this->timeKeepingService->payHoliday($actualSchedule->date, $employee, $cutOff, $holiday);
                        $month = Carbon::parse($actualSchedule->date)->month;

                        if (
                            $month == 12 &&
                            $timeKeep->final_worked_hours > 0 &&
                            $timeKeep->branch->type == "Head Office" &&
                            $employee->schedule->schedule_type->name === "Compressed Schedule"
                        ) {
                            $holidayIsPaid = false;
                        }

                        if ($holidayIsPaid) {
                            $timeKeep->update([
                                "paid_holiday" => "Yes"
                            ]);
                        }
                    }

                    if ($schedule->selection === "SUSPENSION") {
                        continue;
                    }

                    if ($hours == $timeKeep->final_worked_hours && $timeKeep->final_worked_hours > 0 && !$timeKeep->is_cancelled_off) {
                        $regular_days += $schedule->days;
                    } else if ( $hours / 2 >= $timeKeep->final_worked_hours && $timeKeep->final_worked_hours > 0  && !$timeKeep->is_cancelled_off) {
                        $regular_days += .5;
                        $halfDayCount += 1;
                    }

                    if ($holiday && $holidayIsPaid && $schedule->selection === "NO SCHEDULE") {
                        $regular_days += 1;
                        $holidayConvertedToDays = true;
                    }

                    if ($schedule->selection === "INCENTIVE LEAVE") {
                        $regular_days += 1;
                    }

                    if ($timeKeep->is_cancelled_off && $timeKeep->cancelled_off_hours > 0) {
                        $cancelled_off += $timeKeep->cancelled_off_hours;
                    }

                    if ($timeKeep->tardiness > 0) {
                        $tardiness += $timeKeep->tardiness;
                        $lateDate[] = [
                            "date" => $timeKeep->date,
                            "late_minutes" => $timeKeep->tardiness
                        ];
                    }

                    if ($timeKeep->special_holiday_hours > 0 && !$holidayConvertedToDays) {
                        $special_holiday += $holidayIsPaid ? $timeKeep->special_holiday_hours : 0;
                    }

                    if ($timeKeep->legal_holiday_hours > 0 && !$holidayConvertedToDays) {
                        $legal_holiday += $holidayIsPaid ? $timeKeep->legal_holiday_hours : 0;
                    }

                    $totalOT = $timeKeep->approved_overtime;
                    if ($employee->current_branch->type === "Branch") {
                        $totalOT = floor($timeKeep->approved_overtime);
                    }
                    
                    if ($timeKeep->legal_holiday === "Yes" && $holidayIsPaid) {
                        $legal_holiday_overtime += $totalOT > $timeKeep->legal_holiday_overtime ? $timeKeep->legal_holiday_overtime : $totalOT;
                    } else if ($timeKeep->special_holiday === "Yes" && $holidayIsPaid ) {
                        $special_holiday_overtime += $totalOT > $timeKeep->special_holiday_overtime ? $timeKeep->special_holiday_overtime : $totalOT;
                    } else {
                        if ($employee->current_branch->type === "Branch") {
                            $regular_overtime += $totalOT > $timeKeep->regular_overtime ? floor($timeKeep->regular_overtime) : $totalOT;
                        } else {
                            $regular_overtime += $totalOT > $timeKeep->regular_overtime ? $timeKeep->regular_overtime : $totalOT;
                        }
                    }

                    if ($timeKeep->night_diff_hours > 0) {
                        if ($employee->current_branch->type === "Branch") {
                            $night_differential += floor($timeKeep->night_diff_hours);
                        } else {
                            $night_differential += $timeKeep->night_diff_hours;
                        }
                        
                    }

                    if ($timeKeep->night_diff_overtime > 0 && $timeKeep->approved_overtime > 0) {
                        if ($employee->current_branch->type === "Branch") {
                            $night_differential_overtime += $timeKeep->night_diff_overtime > $totalOT ? floor($totalOT) : floor($timeKeep->night_diff_overtime);
                        } else {
                            $night_differential_overtime += $timeKeep->night_diff_overtime > $totalOT ? $totalOT : $timeKeep->night_diff_overtime;
                        }
                    }

                    if ($timeKeep->undertime > 0) {
                        $undertime += (int) $timeKeep->undertime;
                    }

                    if ($timeKeep->travel_time > 0) {
                        $travel_time += (int) $timeKeep->travel_time;
                    }
                }

                $isExisting = TimeKeepingSummary::where("cut_off", $cutOff['start_time']."-".$cutOff['end_time'])
                ->where("employee_id", $employee->id)
                ->first();

                if ($regular_days > 13 && $employee->current_position->timekeeping_type === "Monthly") {
                    $regular_days = 13;
                }

                $schedules = ScheduleTIme::with(["timekeeping", "timekeeping.biotime_log" ])
                ->whereBetween("date", [$cutOff["start_time"], $cutOff["end_time"]])
                ->where("employee_id",$employee->id)
                ->get();

                if ($this->timeKeepingService->isCompleteAttendanceThisCutOff($employee, $schedules)) {
                    $workDays = 0;

                    $schedules->filter(function ($schedule) use ($workDays) {
                        if (!in_array($schedule->timetable, ['LEAVE', 'SUSPENSION', 'REST DAY', 'NO SCHEDULE'])) {
                            $workDays += 1;
                        }
                    });

                    if ($employee->current_position->timekeeping_type === "Monthly") {
                        if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
                            if ($workDays > $regular_days) {
                                $regular_days = 11 - ($workDays - $regular_days);
                            } else {
                                $regular_days = 11;
                            }
                        }

                        if ($employee->schedule->schedule_type->name === "Normal Schedule") {
                            if ($workDays > $regular_days && $workDays < 13 ) {
                                $regular_days = 13 - ($workDays - $regular_days);
                            } else if ($workDays > $regular_days && $workDays > 13) {
                                $regular_days = 13;
                            } else {
                                $regular_days = 13;
                            }
                        }
                    }
                    
                } else {
                    $absent = 0;

                    $restDays = $schedules->filter(function ($schedule) use ($employee) {
                        if (in_array($schedule->timetable, ["REST DAY"])) {
                            return $schedule;
                        }
                    });

                    foreach ($schedules as $sched) {
                        $absentToBeAdded = 0;

                        $biotimeLog = $sched->timekeeping->biotime_log ?? null;
                        if (
                            !in_array($sched->timetable,["REST DAY", "NO SCHEDULE"]) &&
                            $employee->schedule->schedule_type->name === "Compressed Schedule"
                        ) {
                            $timeKeeping = TimeKeeping::where("date", $sched->date)
                            ->where("employee_id", $employee->id)
                            ->first();
                            if (!$timeKeeping || !$biotimeLog) {
                                $absentToBeAdded = 1;
                            }
                        }

                        if (
                            $employee->schedule->schedule_type->name === "Normal Schedule" &&
                            !in_array($sched->timetable,["REST DAY"])
                        ) {
                            $timeKeeping = TimeKeeping::where("date", $sched->date)
                            ->where("employee_id", $employee->id)
                            ->first();
                            if (!$timeKeeping || !$biotimeLog) {
                                $absentToBeAdded = 1;
                            }
                        }

                        $absent += $absentToBeAdded;
                    }

                    if (
                        $employee->schedule->schedule_type->name === "Normal Schedule" &&
                        $restDays->count() > 2 &&
                        $employee->current_position->timekeeping_type === "Monthly"
                    ) {
                        $absentToBeAdded = $restDays->count() - 2;

                        $absent += $absentToBeAdded;
                    }

                    if ($employee->schedule->schedule_type->name === "Compressed Schedule" && $employee->current_position->timekeeping_type === "Monthly") {
                        $regular_days = 11 - $absent;
                    }

                    if ($employee->current_position->timekeeping_type === "Monthly" && $employee->schedule->schedule_type->name === "Normal Schedule") {
                        $regular_days = 13 - $absent;
                    }
                }

                $hasHalfDay = 0;
                $halfDayCount = 0;
                foreach ($timeKeepings as $timeKeeping) {
                    if ( $hours / 2 >= $timeKeeping->final_worked_hours && $timeKeeping->final_worked_hours > 0  && !$timeKeeping->is_cancelled_off) {
                        $halfDayCount += 1;
                    }
                }

                if ($halfDayCount > 0) {
                    $hasHalfDay = $halfDayCount * 0.5;
                }

                $regular_days = $regular_days - $hasHalfDay;

                if ($isExisting) {
                    $isExisting->delete();
                }

                //checking adjustments
                $adjustments_tardiness_amount = 0;
                $adjustments_undertime = 0;
                $adjustments_days = 0;
                $adjustments_cancelled_off = 0;

                $deductions = $this->timeKeepingService->deductPreviousPaidHoliday($cutOff, $employee);
                $remarks = null;
                if (
                    $deductions['special_holiday_hours_deduction'] > 0 ||
                    $deductions['special_holiday_overtime_deduction'] > 0 ||
                    $deductions['legal_holiday_hours_deduction'] > 0 ||
                    $deductions['legal_holiday_overtime_deduction'] > 0
                ) {
                    $special_holiday -= $deductions['special_holiday_hours_deduction'];
                    $special_holiday_overtime -= $deductions['special_holiday_overtime_deduction'];
                    $legal_holiday -= $deductions['legal_holiday_hours_deduction'];
                    $legal_holiday_overtime -= $deductions['legal_holiday_overtime_deduction'];
                    $regular_overtime += $deductions['special_holiday_overtime_deduction'] + $deductions['legal_holiday_overtime_deduction'];
                    $dateString = implode(", ", $deductions["dates"]);
                    $remarks = "Retracted holiday hours from previous cutoff: " . $dateString;
                }

                $timeKeepingSummary = TimeKeepingSummary::create([
                    "cut_off" => $cutOff['start_time']."-".$cutOff['end_time'],
                    "employee_id" => $employee->id,
                    "regular_days" => $regular_days,
                    "special_leave" => $special_leave,
                    "cancelled_off" => $cancelled_off,
                    "special_holiday" => $special_holiday,
                    "legal_holiday" => $legal_holiday,
                    "regular_overtime" => $regular_overtime,
                    "night_differential" => $night_differential,
                    "night_differential_overtime" => $night_differential_overtime,
                    "legal_holiday_overtime" => $legal_holiday_overtime,
                    "special_holiday_overtime" => $special_holiday_overtime,
                    "undertime" => $undertime,
                    "travel_time" => $travel_time,
                    "tardiness" => $tardiness,
                    "branch_id" => $timeKeepings[0]->branch_id,
                    "adjustment_amount" => $adjustment_amount,
                    "remarks" => $remarks
                ]);

                $previousCutOff = $this->getPreviousCutOff($cutOff["start_time"]);

                $adjustments = Adjustment::whereBetween("date", [$previousCutOff['start_time'], $previousCutOff['end_time']])
                ->where("employee_id", $employee->id)
                ->where("is_timekeeping_locked", 0)
                ->whereNot("type", "Overtime Force Adjustments")
                ->whereIn("status", ["Approved", "Adjusted"])
                ->get();

                $anotherAdjustments = Adjustment::where("date","<" ,$cutOff["start_time"])
                ->where("employee_id", $employee->id)
                ->where("type", "Overtime Force Adjustments")
                ->where("is_timekeeping_locked", 0)
                ->whereIn("status", ["Approved", "Adjusted"])
                ->get();

                $withAdjustments = false;
                foreach($adjustments as $adjustment) {                      
                    if ($adjustment->days > 0) {
                        $adjustments_days += $adjustment->days;
                    } else {
                        if ($adjustment->taxable_amount !== 0) {
                            $adjustment_amount += $adjustment->taxable_amount;
                        }

                        if ($adjustment->cancelled_rd > 0) {
                            $adjustments_cancelled_off += $adjustment->cancelled_rd;
                        }
                    }

                    $adjustmentTimeKeeping = AdjustmentTimeKeeping::where("date", $adjustment->date)
                    ->where("employee_id", $employee->id)
                    ->first();

                    if ($adjustmentTimeKeeping) {
                        if ($adjustmentTimeKeeping->undertime > 0) {
                            $adjustments_undertime += $adjustmentTimeKeeping->undertime;
                        }

                        if ($adjustmentTimeKeeping->tardiness > 0) {
                            $adjustments_tardiness_amount += $adjustmentTimeKeeping->tardiness * $minute_rate;
                        }
                    }

                    $adjustment->update([
                        "status" => "Adjusted",
                        "timekeeping_summary_id" => $timeKeepingSummary->id
                    ]);

                    $withAdjustments = true;
                }

                foreach($anotherAdjustments as $adjustment) {
                    if ($adjustment->days > 0) {
                        $adjustments_days += $adjustment->days;
                    } else {
                        if ($adjustment->taxable_amount !== 0) {
                            $adjustment_amount += $adjustment->taxable_amount;
                        }

                        if ($adjustment->cancelled_rd > 0) {
                            $adjustments_cancelled_off += $adjustment->cancelled_rd;
                        }
                    }

                    $adjustmentTimeKeeping = AdjustmentTimeKeeping::where("date", $adjustment->date)
                    ->where("employee_id", $employee->id)
                    ->first();

                    if ($adjustmentTimeKeeping) {
                        if ($adjustmentTimeKeeping->undertime > 0) {
                            $adjustments_undertime += $adjustmentTimeKeeping->undertime;
                        }

                        if ($adjustmentTimeKeeping->tardiness > 0) {
                            $adjustments_tardiness_amount += $adjustmentTimeKeeping->tardiness * $minute_rate;
                        }
                    }

                    $adjustment->update([
                        "status" => "Adjusted",
                        "timekeeping_summary_id" => $timeKeepingSummary->id
                    ]);

                    $withAdjustments = true;
                }

                $adjustment_amount += round($adjustments_tardiness_amount * -1, 2);
                $undertime += $adjustments_undertime;
                $regular_days += $adjustments_days;
                $cancelled_off += round($adjustments_cancelled_off, 2);

                if ($withAdjustments) {
                    $timeKeepingSummary->update([
                        "tardiness" => $tardiness,
                        "undertime" => $undertime,
                        "regular_days" => $regular_days,
                        "adjustment_amount" => $adjustment_amount,
                        "cancelled_off" => $this->formatHours($cancelled_off),
                        "remarks" => $timeKeepingSummary->remarks ? $timeKeepingSummary->remarks . " | With adjustments"  : "With adjustments"
                    ]);
                }

                if (count($lateDate) > 0) {
                    foreach($lateDate as $late) {
                        LateTimeKeepingSummary::create([
                            "timekeeping_summary_id" => $timeKeepingSummary->id,
                            "date" => $late["date"],
                            "late_minutes" => $late["late_minutes"]
                        ]);
                    }
                }
            }
        }

            $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }

    public function getPreviousCutOff($date) {
        $now = Carbon::parse($date);
        $day = $now->day;

        if ($day >= 1 && $day <= 15) {
            $startTime = $now->copy()->subMonth()->startOfMonth()->addDays(25);
            $endTime = $now->copy()->startOfMonth()->addDays(9);
        } else {
            $startTime = $now->copy()->startOfMonth()->addDays(10);
            $endTime = $now->copy()->startOfMonth()->addDays(24);
        }

        return [
            "start_time" => $startTime->toDateString(),
            "end_time" => $endTime->toDateString()
        ];
    }

    private function formatHours($hours) {
        $formattedHours = $hours;

        if ($formattedHours < 1 && $formattedHours > 0) {
            $formattedHours = .5;
        } else {
            $formattedHours = floor($hours / 0.5) * 0.5;
        }   
        return $formattedHours;
    }
}
