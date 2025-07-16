<?php

namespace App\Jobs;

use App\Models\BiotimeLog;
use App\Models\Holiday;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Log;
class TransferLogsToTimeKeeping implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $branchId;
    protected $cutOff;

    public function __construct(string $branchId, $cutoff)
    {
        $this->branchId = $branchId;
        $this->cutOff = $cutoff;
    }

    public function handle(): void
    {
        $schedules = ScheduleTime::whereHas("employee", function($query) {
            $query->where("branch_id", $this->branchId);
        })
        ->whereBetween("date", $this->cutOff)
        ->get();

        foreach($schedules as $actualSchedule) {
            $employee = $actualSchedule->employee;

            $log = BiotimeLog::where("date", $actualSchedule->date)
            ->where("employee_id", $employee->id)
            ->where("is_transferred", false)
            ->first();

            if ($log) {
                $schedule = ScheduleMasterlist::where("selection", $actualSchedule->timetable)
                ->first();

                $isCancelledOff = false;
                if ($schedule->selection === "REST DAY") {
                    $isCancelledOff = true;
                }

                $scheduleStart = Carbon::parse($log->date. " " .$schedule->start_time);
                $scheduleEnd = Carbon::parse($log->date. " " .$schedule->end_time);

                if ($scheduleEnd->isBefore($scheduleStart)) {
                    $date = $date = Carbon::parse($log->date)->addDay()->format('Y-m-d');
                    $scheduleEnd = Carbon::parse($date. " " .$schedule->end_time);
                }

                $scheduledBreak = Carbon::parse($log->date." ".$schedule->time_break);
                $scheduledBreakInMinutes = ($scheduledBreak->hour * 60) + $scheduledBreak->minute;

                $workHours = 0;

                $firstPunch = $log->punches()->orderBy("punch_time", "ASC")->first();
                $lastPunch = $log->punches()->orderBy("punch_time", "DESC")->first();

                $parsedFirstPunch = Carbon::parse($firstPunch->punch_time);
                $parsedLastPunch = Carbon::parse($lastPunch->punch_time);
                $diffInSeconds = $parsedLastPunch ->diffInSeconds($parsedFirstPunch);
                $decimalHours = $diffInSeconds / 3600;
                $workHours = $decimalHours;
                $tardiness = 0;

                if (strtolower($schedule->selection) !== "flex") {
                    $tardiness += $scheduleStart->diffInMinutes($parsedFirstPunch, false) > 0 
                        ? $scheduleStart->diffInMinutes($parsedFirstPunch, false) 
                        : 0;
                }

                $overtime = $workHours - $schedule->total_hours >= 1 ? $workHours - $schedule->total_hours : 0;
                
                $holiday = Holiday::whereHas("branches", function($query) use ($employee) {
                    $query->where("branches.id", $employee->branch_id);
                })->where("date", $log->date)->first();
                
                if ($tardiness >= 120) {
                    $workHours = $schedule->total_hours / 2;
                }

                $legalHoliday = "No";
                $specialHoliday = "No";
                $legalHolidayHours = 0;
                $legalHolidayOvertime = 0;
                $specialHolidayHours = 0;
                $specialHolidayOvertime = 0;
                $undertime = 0;
                $nightDiffHours = 0;
                $nightDiffOt = 0;

                if ($workHours < $schedule->total_hours && $parsedLastPunch->isBefore($scheduleEnd)) {       
                    $undertime = $parsedLastPunch->diffInMinutes($scheduleEnd, true);
                }


                if ($holiday) {
                    $legalHoliday = $holiday->holiday_type === "LH" ? "Yes" : "No";
                    $specialHoliday = $holiday->holiday_type === "SH" ? "Yes" : "No";
                    
                    if ($legalHoliday === "Yes") {
                        $legalHolidayHours = $workHours;
                        $legalHolidayOvertime = $overtime;
                    }

                    if ($specialHoliday === "Yes") {
                        $specialHolidayHours = $workHours;
                        $specialHolidayOvertime = $overtime;
                    }
                }

                if (
                    $log->punches()->count() === 4 &&
                    strtolower($schedule->selection) !== "flex"
                ) {
                    $breaks = $log->punches()
                    ->whereNot("id", [$firstPunch->id, $lastPunch->id])
                    ->orderBy("punch_time", "ASC")
                    ->get();

                    $parsedBreakOut = Carbon::parse($breaks[0]->punch_time);
                    $parsedBreakIn = Carbon::parse($breaks[1]->punch_time);
                    $breakDuration = $parsedBreakOut->diffInMinutes($parsedBreakIn);

                    if ($breakDuration > $scheduledBreakInMinutes) {
                        Log::info($parsedBreakOut);
                        Log::info($parsedBreakIn);

                        $tardiness += ($breakDuration - $scheduledBreakInMinutes);
                    }
                }

                if (
                    $log->punches()->count() === 3 &&
                    strtolower($schedule->selection) !== "flex"
                ) {
                    $secondPunch = $log->punches()
                    ->whereNot("id", [$firstPunch->id, $lastPunch->id])
                    ->orderBy("punch_time", "ASC")
                    ->first();
                    $parsedSecondPunch = Carbon::parse($secondPunch->punch_time);

                    $breakOutGap = $scheduleStart->diffInMinutes($parsedSecondPunch);
                    $breakInGap = $parsedSecondPunch->diffInMinutes($scheduleEnd);

                    // NO CLOCK IN CORRECT
                    if (
                        $scheduleStart->diffInHours($parsedFirstPunch) > 3 &&
                        $parsedFirstPunch->diffInHours($parsedSecondPunch) <= 2
                    ) {
                        $tardiness = $scheduleStart->diffInMinutes($parsedFirstPunch);
                    }

                    // NO BREAK OUT
                    if (
                        $scheduleStart->diffInHours($parsedFirstPunch) <= 3 &&
                        $parsedFirstPunch->diffInHours($parsedSecondPunch) > 3 &&
                        $breakOutGap > $breakInGap
                    ) {
                        $tardiness = $scheduleStart->diffInMinutes($parsedSecondPunch) - $scheduledBreakInMinutes;
                    }

                    // NO BREAK IN
                    if (
                        $parsedSecondPunch->diffInHours($parsedLastPunch) > 3 &&
                        $parsedSecondPunch->diffInMinutes($parsedLastPunch) > $scheduledBreakInMinutes &&
                        $breakInGap > $breakOutGap
                    ) {
                        $tardiness = $parsedSecondPunch->diffInMinutes($scheduleEnd) - $scheduledBreakInMinutes;
                    }

                    // NO CLOCK OUT
                    if (
                        $scheduleStart->diffInHours($parsedFirstPunch) <= 3 &&
                        $parsedSecondPunch->diffInHours($parsedLastPunch) < 3 &&
                        $scheduleStart->diffInHours($parsedFirstPunch) < 3 &&
                        $workHours < $schedule->total_hours
                    ) {
                        $undertime = ($schedule->total_hours - $workHours) * 60;
                    }
                }

                if (
                    $log->punches()->count() === 2 &&
                    strtolower($schedule->selection) !== "flex"
                ) {
                    if ($workHours >= 4 && $workHours <= 5) {
                        $workHours = $schedule->total_hours / 2;
                    } else if ($workHours >= 6) {
                        $workHours = $workHours - ($scheduledBreakInMinutes / 60);
                        $undertime = $scheduledBreakInMinutes;
                    }
                }

                if (
                    $log->punches()->count() > 4 &&
                    strtolower($schedule->selection) !== "flex"
                ) {

                    $punches = $log->punches()->orderBy("punch_time", "ASC")
                    ->whereNot("id", [$firstPunch->id, $lastPunch->id])
                    ->get();

                    $durations = [];
                    
                    for ($i = 0; $i < $punches->count() - 1; $i++) {
                        $currentPunch = $punches[$i];
                        $nextPunch = $punches[$i + 1];
                        $duration = Carbon::parse($currentPunch->punch_time)
                            ->diffInMinutes(Carbon::parse($nextPunch->punch_time));

                        $durations[] = [
                            "duration" => $duration,
                            "idx" => $i,
                            "idx2" => $i++
                        ];
                    }

                    $closest = collect($durations)->reduce(function ($carry, $item) use ($scheduledBreakInMinutes) {
                        if (is_null($carry)) {
                            return $item;
                        }

                        $currentDiff = abs($item['duration'] - $scheduledBreakInMinutes);
                        $carryDiff = abs($carry['duration'] - $scheduledBreakInMinutes);

                        return $currentDiff < $carryDiff ? $item : $carry;
                    });

                    if ($closest['duration'] > $scheduledBreakInMinutes) {
                        $tardiness += $closest['duration'] - $scheduledBreakInMinutes;
                    }

                    if ($log->punches()->count() >= 6) {
                        $tardiness = 0;
                    }
                }

                $tenPM = Carbon::parse($log->date." ".'22:00:00');
                if ($scheduleEnd->greaterThan($tenPM) && $parsedLastPunch->greaterThan($tenPM)) {
                    $nightDiffSeconds = $scheduleEnd->diffInSeconds($tenPM);
                    $nightDiffHours = $nightDiffSeconds / 3600;
                    $nightDiffOt = $overtime;
                }

                if ($tenPM->greaterThan($scheduleEnd) && $parsedLastPunch->greaterThan($tenPM)) {
                    $nightDiffOtSeconds = $parsedLastPunch->diffInSeconds($tenPM);
                    $nightDiffOt = $nightDiffOtSeconds / 3600;
                }

                if ($schedule->selection == "NO SCHEDULE" || strtolower($schedule->selection) === "flex") {
                    $tardiness = 0;
                }
                
                if ($log->punches()->count() > 1) {
                    TimeKeeping::create([
                        "employee_id" => $employee->id,
                        "biometric_number" => $employee->biometric_number,
                        "biotime_log_id" => $log->id,
                        "schedule_time_id" => $actualSchedule->id,
                        "date" => $log->date,
                        "worked_hours" => $workHours,
                        "legal_holiday" => $legalHoliday,
                        "special_holiday" => $specialHoliday,
                        "regular_overtime" => $overtime,
                        "tardiness" => $tardiness,
                        "night_diff_hours" => $nightDiffHours,
                        "night_diff_overtime" => $nightDiffOt,
                        "legal_holiday_hours" => $legalHolidayHours,
                        "legal_holiday_overtime" => $legalHolidayOvertime,
                        "special_holiday_hours" => $specialHolidayHours,
                        "special_holiday_overtime" => $specialHolidayOvertime,
                        "undertime" => $undertime,
                        "is_cancelled_off" => $isCancelledOff,
                    ]);
    
                    $log->update([
                        "is_transferred" => true
                    ]);
                }


            } else {
                continue;
            }
        } 
    }

    public function getCurrentCutoff() {
        $now = Carbon::now();
        $startTime = "";
        $endTime = "";
        $dayOfMonth = Carbon::now()->day;

        if ($dayOfMonth >= 1 && $dayOfMonth <= 15) {
            $startTime = $now->copy()->subMonth()->startOfMonth()->addDays(25)->startOfDay(); //26
            $endTime = $now->copy()->startOfMonth()->addDays(9)->endOfDay(); //10
        } else {
            $startTime = $now->copy()->startOfMonth()->startOfMonth()->addDays(10)->startOfDay(); //11
            $endTime = $now->copy()->startOfMonth()->addDays(24)->endOfDay(); //25
        }

        return [
            "start_time" => $startTime->toDateString(),
            "end_time" => $endTime->toDateString()
        ];
    }
}
