<?php

namespace App\Jobs;

use App\Models\BiotimeLog;
use App\Models\Holiday;
use App\Models\Overtime;
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
use Exception;
use DB;
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
        try {
            DB::beginTransaction();

            $schedules = ScheduleTime::where("branch_id", $this->branchId)
            ->whereBetween("date", $this->cutOff)
            ->get();

            foreach($schedules as $actualSchedule) {
                $employee = $actualSchedule->employee;

                $log = BiotimeLog::where("date", $actualSchedule->date)
                ->where("employee_id", $employee->id)
                ->first();

                $schedule = ScheduleMasterlist::where("selection", $actualSchedule->timetable)->first();
                $travel_time = 0;

                if ($log) {
                    $isCancelledOff = false;

                    $scheduleStart = Carbon::parse($log->date. " " .$schedule->start_time)->seconds(0);
                    $scheduleEnd = Carbon::parse($log->date. " " .$schedule->end_time)->seconds(0);

                    if ($scheduleEnd->isBefore($scheduleStart)) {
                        $date = Carbon::parse($log->date)->addDay()->format('Y-m-d');
                        $scheduleEnd = Carbon::parse($date. " " .$schedule->end_time)->seconds(0);
                    }

                    $scheduledBreak = Carbon::parse($log->date." ".$schedule->time_break);
                    $scheduledBreakInMinutes = ($scheduledBreak->hour * 60) + $scheduledBreak->minute;

                    $workHours = 0;
                    $finalWorkHours = 0;
                    $logPunches = $log->punches()->orderBy("punch_time", "ASC")->get();
                    $firstPunch = $logPunches[0];
                    $lastPunch = $logPunches[$logPunches->count() - 1];
                    $parsedFirstPunch = Carbon::parse($firstPunch->punch_time)->seconds(0);
                    $parsedLastPunch = Carbon::parse($lastPunch->punch_time)->seconds(0);
                    $diffInSeconds = $parsedLastPunch->diffInSeconds($parsedFirstPunch);
                    $decimalHours = $diffInSeconds / 3600;

                    $workHours += $decimalHours;
                    $tardiness = 0;
                    $legalHoliday = "No";
                    $specialHoliday = "No";
                    $legalHolidayHours = 0;
                    $legalHolidayOvertime = 0;
                    $specialHolidayHours = 0;
                    $specialHolidayOvertime = 0;
                    $undertime = 0;
                    $nightDiffHours = 0;
                    $nightDiffOt = 0;
                    $overtime = 0;

                    if (strtolower($schedule->selection) !== "flex") {
                        $tardiness += $scheduleStart->diffInMinutes($parsedFirstPunch, false) > 0
                            ? $scheduleStart->diffInMinutes($parsedFirstPunch, false)
                            : 0;
                    }

                    //overtime computation
                    if ($parsedLastPunch->isAfter($scheduleEnd)) {
                        $overtimeInSeconds = $parsedLastPunch->diffInSeconds($scheduleEnd);
                        $overtimeInHour = $overtimeInSeconds / 3600;

                        if ($overtimeInHour >= 1) {
                            $overtime = $overtimeInHour;
                        }
                    }

                    if ($schedule->selection === "REST DAY") {
                        $isCancelledOff = true;
                        $overtime = $workHours;
                    }

                    $holiday = Holiday::whereHas("branches", function($query) use ($employee) {
                        $query->where("branches.id", $this->branchId);
                    })->where("date", $log->date)->first();

                    if ($tardiness >= 120) {
                        $workHours = $schedule->total_hours / 2;
                        $tardiness = 0;
                    }

                    //undertime computation
                    if ($workHours < $schedule->total_hours && $parsedLastPunch->isBefore($scheduleEnd) && $workHours > ($schedule->total_hours / 2)) {
                        $undertime = $parsedLastPunch->diffInMinutes($scheduleEnd, true);
                    }
                    if ($workHours < ($schedule->total_hours / 2)) {
                        $halfScheduleTime = $scheduleStart->copy()->addMinutes($scheduleStart->diffInMinutes($scheduleEnd) / 2);
                        $undertime = $parsedLastPunch->diffInMinutes($halfScheduleTime, true);
                    }

                    //holiday computation
                    if ($holiday) {
                        $legalHoliday = $holiday->holiday_type === "LH" ? "Yes" : "No";
                        $specialHoliday = $holiday->holiday_type === "SH" ? "Yes" : "No";

                        $holidayWorkingHours = $scheduleStart->diffInSeconds($parsedLastPunch);
                        $holidayWorkedHours = $holidayWorkingHours - ($scheduledBreakInMinutes * 60);
                        $totalHolidayWorkedHours = $holidayWorkedHours / 3600;

                        if ($legalHoliday === "Yes") {
                            $legalHolidayHours = $totalHolidayWorkedHours;
                            $legalHolidayOvertime = $overtime;
                        }

                        if ($specialHoliday === "Yes") {
                            $specialHolidayHours = $totalHolidayWorkedHours;
                            $specialHolidayOvertime = $overtime;
                        }

                        $overtime = 0;
                    }

                    if (
                        $schedule->selection === "Spot Audit" ||
                        $schedule->selection === "MEI 08:00 TO 06:30" ||
                        $schedule->selection === "MEI 09:00 TO 19:30" ||
                        $schedule->selection === "Flex" ||
                        $schedule->selection === "Flex 1" ||
                        $schedule->selection === "Flex 2"
                    ) {
                        $punches = $log->punches()->orderBy("punch_time", "ASC")->get();
                        $firstPunch = $punches[0];

                        $punchWithTravelTime = $punches->first(function ($punch) {
                            return $punch->branch && $punch->branch->travel_time;
                        });

                        if ($punchWithTravelTime) {
                            if ($firstPunch->branch_id === null) {
                                $travel_time = $punchWithTravelTime->branch ? (int)$punchWithTravelTime->branch->travel_time : 0;
                            } else {
                                $travel_time = $punchWithTravelTime->branch ? (int)$punchWithTravelTime->branch->travel_time * 2 :  0;
                            }
                        }

                        $workHours += (int)$travel_time;
                        $undertime = 0;
                        $tardiness = 0;
                        $totalWorkedHours = $workHours - ($scheduledBreakInMinutes / 60);
                        if ($totalWorkedHours < 9.5 && $totalWorkedHours >= 7 ) {
                            $totalWorkedHours = 9.5;
                        }
                        if ($totalWorkedHours > 9.5) {
                            $overtime = $totalWorkedHours - 9.5 < 1 ? 0 : $totalWorkedHours - 9.5;
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

                        $parsedBreakOut = Carbon::parse($breaks[0]->punch_time)->seconds(0);
                        $parsedBreakIn = Carbon::parse($breaks[1]->punch_time)->seconds(0);
                        $breakDuration = $parsedBreakOut->diffInMinutes($parsedBreakIn);

                        if ($breakDuration > $scheduledBreakInMinutes) {
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
                        $parsedSecondPunch = Carbon::parse($secondPunch->punch_time)->seconds(0);

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
                            $underTimeInMinutes = $scheduleEnd->diffInMinutes($parsedLastPunch);
                            $undertime = $underTimeInMinutes;
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

                        $allPunches = $log->punches()->orderBy("punch_time", "ASC")->get();

                        $punches = $allPunches->filter(function ($punch) use ($firstPunch, $lastPunch) {
                            return $punch->id !== $firstPunch->id && $punch->id !== $lastPunch->id;
                        })->values();
                        $durations = [];

                        for ($i = 0; $i < $punches->count() - 1; $i++) {
                            $currentPunch = $punches[$i];
                            $nextPunch = $punches[$i + 1];

                            $duration = Carbon::parse($currentPunch->punch_time)->seconds(0)
                                ->diffInMinutes(Carbon::parse($nextPunch->punch_time)->seconds(0));

                            $durations[] = [
                                "duration" => $duration,
                                "idx" => $i,
                                "idx2" => $i + 1,
                            ];
                        }

                        $closest = collect($durations)->reduce(function ($carry, $item) use ($scheduledBreakInMinutes) {
                            if (is_null($carry)) return $item;

                            $currentDiff = abs($item['duration'] - $scheduledBreakInMinutes);
                            $carryDiff = abs($carry['duration'] - $scheduledBreakInMinutes);

                            return $currentDiff < $carryDiff ? $item : $carry;
                        });

                        if ($closest && $closest['duration'] > $scheduledBreakInMinutes) {
                            $tardiness += $closest['duration'] - $scheduledBreakInMinutes;
                        }

                        if ($allPunches->count() >= 6) {
                            $tardiness = 0;
                        }
                    }

                    $tenPM = Carbon::parse($log->date." ".'22:00:00');
                    //FLEX TO BE CHANGED
                    if (
                        ($scheduleEnd->greaterThan($tenPM) && $parsedLastPunch->greaterThan($tenPM)) ||
                        strtolower($schedule->selection) === "flex"
                    ) {
                        $differ = $scheduleEnd;
                        if ($parsedLastPunch->isBefore($scheduleEnd)) {
                            $differ = $parsedLastPunch;
                        }

                        $nightDiffSeconds = $differ->diffInSeconds($tenPM);
                        $nightDiffHours = $nightDiffSeconds / 3600;
                     
                        if ($overtime >= 1) {
                            $nightDiffOt = $overtime;
                        }
                    }

                    //FLEX TO BE CHANGED
                    if (
                        ($tenPM->greaterThanOrEqualTo($scheduleEnd) && $parsedLastPunch->greaterThan($tenPM)) ||
                        strtolower($schedule->selection) === "flex"
                    ) {
                        $nightDiffOtSeconds = $parsedLastPunch->diffInSeconds($tenPM);
                        $ot = $nightDiffOtSeconds / 3600;
                        if ($ot >= 1) {
                            $nightDiffOt = $ot;
                        }
                    }

                    if (
                        $schedule->selection == "NO SCHEDULE" ||
                        strtolower($schedule->selection) === "flex"
                    ) {
                        $tardiness = 0;

                        if ($schedule->selection == "NO SCHEDULE") {
                            $overtime = $decimalHours;
                        }
                    }

                    if ($log->punches()->count() > 1) {

                        $overtimeApplied = Overtime::where("employee_id", $employee->id)
                        ->where("overtime_date", $log->date)
                        ->first();

                        $generalOvertime = $overtime;
                        $approvedOvertime = 0;
                        $remarks = null;
                        if ($legalHoliday === "Yes") {
                            $generalOvertime = $legalHolidayHours;
                        }
                        if ($specialHoliday === "Yes") {
                            $generalOvertime = $specialHolidayHours;
                        }

                        if ($overtimeApplied) {
                            if ($overtimeApplied->total_hours <= $generalOvertime) {
                                $approvedOvertime = $overtimeApplied->total_hours;
                            } else {
                                $approvedOvertime = $generalOvertime;
                                $remarks = "Approved overtime is higher than the computed overtime from the system.";
                            }

                            $overtimeApplied->update([
                                "status" => "Approved"
                            ]);
                        }

                        $isExisting = TimeKeeping::where("employee_id", $employee->id)
                        ->where("biotime_log_id", $log->id)
                        ->where("date" , $log->date)
                        ->first();

                        if ($isExisting) {
                            $isExisting->delete();
                        }

                        if (
                            ($workHours >= $schedule->total_hours - 3.5 || $undertime < 240 ) &&
                            !$isCancelledOff
                        ) {
                            $finalWorkHours = $schedule->total_hours;
                        } else if (
                            $workHours < $schedule->total_hours - 3.5 &&
                            $workHours >= $schedule->total_hours / 2 &&
                            !$isCancelledOff
                        ) {
                            $finalWorkHours = $schedule->total_hours / 2;
                        }

                        TimeKeeping::create([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "biotime_log_id" => $log->id,
                            "schedule_time_id" => $actualSchedule->id,
                            "date" => $log->date,
                            "worked_hours" => $workHours,
                            "legal_holiday" => $legalHoliday,
                            "special_holiday" => $specialHoliday,
                            "regular_overtime" => $this->formatHours($overtime),
                            "tardiness" => $tardiness,
                            "night_diff_hours" => $this->formatHours($nightDiffHours),
                            "night_diff_overtime" => $this->formatHours($nightDiffOt),
                            "legal_holiday_hours" => $this->formatHours($legalHolidayHours),
                            "legal_holiday_overtime" => $this->formatHours($legalHolidayOvertime),
                            "special_holiday_hours" => $this->formatHours($specialHolidayHours),
                            "special_holiday_overtime" => $this->formatHours($specialHolidayOvertime),
                            "undertime" => $undertime,
                            "is_cancelled_off" => $isCancelledOff,
                            "branch_id" => $this->branchId,
                            "travel_time" => $travel_time,
                            "approved_overtime" => $approvedOvertime,
                            "remarks" => $remarks,
                            "final_worked_hours" => $finalWorkHours
                        ]);
                    }
                } else {
                    if ($schedule->selection === "INCENTIVE LEAVE") {
                        TimeKeeping::create([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "biotime_log_id" => null,
                            "schedule_time_id" => $actualSchedule->id,
                            "date" => $actualSchedule->date,
                            "worked_hours" => $schedule->total_hours,
                            "legal_holiday" => 0,
                            "special_holiday" => 0,
                            "regular_overtime" => 0,
                            "tardiness" => 0,
                            "night_diff_hours" => 0,
                            "night_diff_overtime" => 0,
                            "legal_holiday_hours" => 0,
                            "legal_holiday_overtime" => 0,
                            "special_holiday_hours" => 0,
                            "special_holiday_overtime" => 0,
                            "undertime" => 0,
                            "is_cancelled_off" => false,
                            "branch_id" => $this->branchId,
                            "final_worked_hours" => $schedule->total_hours
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
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

    public function formatHours($hours) {
        $formattedHours = $hours;

        if ($formattedHours < 1 && $formattedHours > 0) {
            $formattedHours = .5;
        } else {
            $formattedHours = floor($hours / 0.5) * 0.5;
        }   
        return $formattedHours;
    }
}
