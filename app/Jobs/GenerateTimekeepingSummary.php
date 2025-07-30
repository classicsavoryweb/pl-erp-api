<?php

namespace App\Jobs;

use App\Models\LateTimeKeepingSummary;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Log;
class GenerateTimekeepingSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $employees;
    protected $currentCutOff;
    /**
     * Create a new job instance.
     */
    public function __construct(Collection $employees, $currentCutOff)
    {
        $this->employees = $employees;
        $this->currentCutOff = $currentCutOff;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutOff = $this->currentCutOff;
        foreach ($this->employees as $employee) {
            $timeKeepings = TimeKeeping::where("employee_id", $employee->id)
            ->whereBetween("date", $cutOff)
            ->get();

            $regular_days = 0; //done
            $special_leave= 0; //manual
            $cancelled_off= 0; //
            $special_holiday= 0; //done
            $legal_holiday= 0; //done
            $regular_overtime= 0; //done
            $night_differential= 0; //done
            $night_differential_overtime= 0; //done
            $legal_holiday_overtime= 0; //done
            $special_holiday_overtime= 0; // done
            $undertime= 0;
            $adjustment_amount = 0;
            $travel_time= 0;
            $tardiness = 0; // done
            $lateDate = [];

            if ($timeKeepings->count() > 0) {
                foreach ($timeKeepings as $timeKeep) {
                    $actualSchedule = ScheduleTime::find($timeKeep->schedule_time_id);
                    $employee = $actualSchedule->employee;
                    $schedule = ScheduleMasterlist::where("selection", $actualSchedule->timetable)
                    ->first();

                    if ($schedule->selection === "SUSPENSION") {
                        continue;
                    }

                    if ($schedule->total_hours == $timeKeep->final_worked_hours && !$timeKeep->is_cancelled_off) {
                        $regular_days += $schedule->days;
                    } else if ( $schedule->total_hours / 2 == $timeKeep->final_worked_hours  && !$timeKeep->is_cancelled_off) {
                        $regular_days += .5;
                    }

                    if ($regular_days > 13 && $employee->current_position->timekeeping_type === "Monthly") {
                        $regular_days = 13;
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

                    if ($timeKeep->special_holiday_hours > 0) {
                        $special_holiday += $timeKeep->special_holiday_hours;
                    }

                    if ($timeKeep->legal_holiday_hours > 0) {
                        $legal_holiday+= $timeKeep->legal_holiday_hours;
                    }

                    $totalOT = $timeKeep->approved_overtime;
                    if ($employee->current_branch->type === "Branch") {
                        $totalOT = floor($timeKeep->approved_overtime);
                    }

                    if ($timeKeep->legal_holiday === "Yes") {
                        $legal_holiday_overtime += $totalOT;
                    } else if ($timeKeep->special_holiday === "Yes") {
                        $special_holiday_overtime += $totalOT;
                    } else {
                        $regular_overtime += $totalOT;
                    }

                    if ($timeKeep->night_diff_hours > 0) {
                        $night_differential += $timeKeep->night_diff_hours;
                    }

                    if ($timeKeep->night_diff_overtime > 0 && $timeKeep->night_diff_overtime <= $totalOT) {
                        $night_differential_overtime += $timeKeep->night_diff_overtime;
                    }

                    if ($timeKeep->undertime > 0) {
                        $undertime += (int) $timeKeep->undertime;
                    }

                    if ($timeKeep->travel_time > 0) {
                        $travel_time += (int) $timeKeep->travel_time;
                    }
                }

                $isExisting = TimeKeepingSummary::where("cut-off", $cutOff['start_time']."-".$cutOff['end_time'])
                ->where("employee_id", $employee->id)
                ->first();

                if ($isExisting) {
                    $isExisting->delete();
                }

                $timeKeepingSummary = TimeKeepingSummary::create([
                    "cut-off" => $cutOff['start_time']."-".$cutOff['end_time'],
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
                    "branch_id" => $timeKeepings[0]->branch_id
                ]);

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
    }
}
