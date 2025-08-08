<?php

namespace App\Http\Services;

use App\Models\BiotimeLog;
use App\Models\Holiday;
use App\Models\Overtime;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonInterface;


class TimeKeepingService
{
    public function computeTimeKeeping($branchId, $log, $biotimePunches, $actualSchedule, $employee, Model $model)
    {
        $schedule = ScheduleMasterlist::where("selection", $actualSchedule)->first();
        $scheduleTime = ScheduleTime::where("timetable", $actualSchedule)
        ->where("employee_id", $employee->id)
        ->where("date", $log->date)
        ->first();

        if (!$scheduleTime) {
            $scheduleTime = ScheduleTime::where("employee_id", $employee->id)
            ->where("date", $log->date)
            ->first();
        }

        $travel_time = 0;

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
        $logPunches = $biotimePunches;
        $firstPunch = $logPunches[0];
        $lastPunch = $logPunches[$logPunches->count() - 1];
        $parsedFirstPunch = Carbon::parse($firstPunch->punch_time)->seconds(0);
        $parsedLastPunch = Carbon::parse($lastPunch->punch_time)->seconds(0);
        $diffInSeconds = $parsedLastPunch->diffInSeconds($parsedFirstPunch);
        $decimalHours = $diffInSeconds / 3600;

        $workHours += $decimalHours;
        $cancelled_off_hours = 0;
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

        $holiday = Holiday::whereHas("branches", function($query) use ($branchId) {
            $query->where("branches.id", $branchId);
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
            $punches = $biotimePunches;
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
            $biotimePunches->count() === 4 &&
            strtolower($schedule->selection) !== "flex"
        ) {
            $breaks = $biotimePunches
            ->whereNotIn("id", [$firstPunch->id, $lastPunch->id])
            ->values();

            $parsedBreakOut = Carbon::parse($breaks[0]->punch_time)->seconds(0);
            $parsedBreakIn = Carbon::parse($breaks[1]->punch_time)->seconds(0);
            $breakDuration = $parsedBreakOut->diffInMinutes($parsedBreakIn);

            if ($breakDuration > $scheduledBreakInMinutes) {
                $tardiness += ($breakDuration - $scheduledBreakInMinutes);
            }
        }

        if (
            $biotimePunches->count() === 3 &&
            strtolower($schedule->selection) !== "flex"
        ) {
            $secondPunch = $biotimePunches[1];
            $parsedSecondPunch = Carbon::parse($secondPunch->punch_time)->seconds(0);

            $breakOutGap = $scheduleStart->diffInMinutes($parsedSecondPunch);
            $breakInGap = $parsedSecondPunch->diffInMinutes($scheduleEnd);

            // NO CLOCK IN
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
                $scheduleEnd->diffInHours($parsedLastPunch) < 3 &&
                $breakOutGap > $breakInGap
            ) {
                $tardiness = $scheduleStart->diffInMinutes($parsedSecondPunch) - $scheduledBreakInMinutes;
            }

            // NO BREAK IN
            if (
                $parsedSecondPunch->diffInHours($parsedLastPunch) > 3 &&
                $parsedSecondPunch->diffInMinutes($parsedLastPunch) > $scheduledBreakInMinutes &&
                $scheduleEnd->diffInHours($parsedLastPunch) < 3 &&
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
            $biotimePunches->count() === 2 &&
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
            $biotimePunches->count() > 4 &&
            strtolower($schedule->selection) !== "flex"
        ) {

            $allPunches = $biotimePunches;

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

        if ($biotimePunches->count() > 1) {

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

            if ($schedule->selection === "REST DAY") {
                $isCancelledOff = true;
                $overtime = 0;
                $cancelled_off_hours = round($decimalHours, 2);
            }

            $isExisting = $model->where("employee_id", $employee->id)
            ->where("biotime_log_id", $log->id)
            ->where("date" , $log->date)
            ->first();

            if ($isExisting) {
                $payment = TimeKeepingPayment::where("timekeeping_id", $isExisting->id)->first();
                if ($payment) {
                    $payment->delete();
                }
                $isExisting->delete();
            }

            $hours = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;
            if (
                ($workHours >= $schedule->total_hours - 3.5 || $undertime < 240 ) &&
                !$isCancelledOff
            ) {
                $finalWorkHours = $hours;
            } else if (
                $workHours < $schedule->total_hours - 3.5 &&
                $workHours >= $schedule->total_hours / 2 &&
                !$isCancelledOff
            ) {
                $finalWorkHours = $hours / 2;
            }

            if ($holiday) {
                if ($workHours >= $schedule->total_hours) {
                    if ($legalHoliday === "Yes") {
                        $legalHolidayHours = $hours;
                    } else if ($specialHoliday === "Yes") {
                        $specialHolidayHours = $hours;
                    }
                } else {
                    $holidayHours = $workHours > $hours ? $hours : $workHours;
                    if ($legalHoliday === "Yes") {
                        $legalHolidayHours = $holidayHours;
                        $finalWorkHours = $hours; 
                    } else if ($specialHoliday === "Yes") {
                        $specialHolidayHours = $holidayHours;
                        $finalWorkHours = $hours;
                    }
                }
            }

            $model->create([
                "employee_id" => $employee->id,
                "biometric_number" => $employee->biometric_number,
                "biotime_log_id" => $log->id,
                "schedule_time_id" => $scheduleTime->id,
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
                "branch_id" => $branchId,
                "travel_time" => $travel_time,
                "approved_overtime" => $this->formatHours($approvedOvertime),
                "remarks" => $remarks,
                "final_worked_hours" => $finalWorkHours,
                "cancelled_off_hours" => $cancelled_off_hours,
            ]);
        }

        if ($schedule->selection === "INCENTIVE LEAVE") {
            $model->create([
                "employee_id" => $employee->id,
                "biometric_number" => $employee->biometric_number,
                "biotime_log_id" => null,
                "schedule_time_id" => $scheduleTime->id,
                "date" => $scheduleTime->date,
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
                "branch_id" => $branchId,
                "final_worked_hours" => $schedule->total_hours
            ]);
        }
    }

    public function computePayment($timeKeeping, $actualSchedule, $isAdjustment = false) {
        if ($timeKeeping) {
            $employee = $timeKeeping->employee;

            $date = $timeKeeping->date;
            $schedule = ScheduleMasterlist::where("selection", $actualSchedule)->first();

            $scheduledBreak = Carbon::parse($date . ' ' . $schedule->time_break);
            $scheduledBreakInHours = $scheduledBreak->hour + ($scheduledBreak->minute / 60);             
            $totalDutyHours = $schedule->total_hours - $this->formatHours($scheduledBreakInHours);

            $paymentPerDay = $employee->daily_salary;
            $paymentPerHour =  $totalDutyHours > 0 ? $paymentPerDay / $totalDutyHours : $paymentPerDay / 8;
            $paymentPerMinute = $paymentPerHour / 60;

            $tardinessDeduction = 0;
            $undertimeDeduction = 0;
            $totalAmount = 0;
            $regular_overtime = 0;
            $night_differential = 0;
            $night_differential_overtime = 0;
            $legal_holiday = 0;
            $special_holiday = 0;
            $legal_holiday_overtime = 0;
            $special_holiday_overtime = 0;
            $cancelled_off = 0;

            $tardinessDeduction = $timeKeeping->tardiness * $paymentPerMinute;
            $undertimeDeduction = $timeKeeping->undertime * $paymentPerMinute;
            $legal_holiday = $timeKeeping->legal_holiday_hours * $paymentPerHour;
            $special_holiday = $timeKeeping->special_holiday_hours * ($paymentPerHour * .3);
            $night_differential = $timeKeeping->night_diff_hours * ($paymentPerHour * .1);

            $regular_overtime = $timeKeeping->approved_overtime * ($paymentPerHour * 1.25);
            if ($timeKeeping->night_diff_overtime > 0 && $timeKeeping->night_diff_overtime <= $timeKeeping->approved_overtime) {
                $night_differential_overtime = $timeKeeping->night_diff_overtime * ($paymentPerHour * .125);
            }
            if ($timeKeeping->legal_holiday_overtime > 0 && $timeKeeping->legal_holiday_overtime <= $timeKeeping->approved_overtime) {
                $legal_holiday_overtime = $timeKeeping->legal_holiday_overtime * ($paymentPerHour * 2.6);
            }
            if ($timeKeeping->special_holiday_overtime > 0 && $timeKeeping->special_holiday_overtime <= $timeKeeping->approved_overtime) {
                $special_holiday_overtime = $timeKeeping->special_holiday_overtime * ($paymentPerHour * 1.69);
            }

            $totalAmount = ($paymentPerDay + $regular_overtime + $night_differential + $night_differential_overtime + $legal_holiday + $special_holiday + $legal_holiday_overtime + $special_holiday_overtime + $cancelled_off)
                - ($tardinessDeduction + $undertimeDeduction);

            if ($timeKeeping->is_cancelled_off) {
                $cancelledOffWorkedHours = $timeKeeping->cancelled_off_hours;

                if ($employee->schedule_type === "Normal Schedule") {
                    $cancelledOffWorkedHours = $timeKeeping->cancelled_off_hours < 8 ? $timeKeeping->cancelled_off_hours : 8;
                } else {
                    $cancelledOffWorkedHours = $timeKeeping->cancelled_off_hours < 9.5 ? $timeKeeping->cancelled_off_hours : 9.5;
                }
                $cancelled_off = $cancelledOffWorkedHours * ($paymentPerHour * 1.3);
                $totalAmount = $cancelled_off;
            }

            if (!$isAdjustment) {
                $payment = TimeKeepingPayment::where("timekeeping_id", $timeKeeping->id)->first();
                if ($payment) {
                    $payment->delete();
                }

                TimeKeepingPayment::create([
                    "timekeeping_id" => $timeKeeping->id,
                    "base_amount" => round($paymentPerDay, 2),
                    "tardiness_deduction" => round($tardinessDeduction, 2),
                    "undertime_deduction" => round($undertimeDeduction, 2),
                    "regular_overtime" => round($regular_overtime, 2),
                    "night_differential" => round($night_differential, 2),
                    "night_differential_overtime" => round($night_differential_overtime, 2),
                    "legal_holiday" => round($legal_holiday, 2),
                    "special_holiday" => round($special_holiday, 2),
                    "legal_holiday_overtime" => round($legal_holiday_overtime, 2),
                    "special_holiday_overtime" => round($special_holiday_overtime, 2),
                    "cancelled_off" => round($cancelled_off, 2),
                    "total_amount" => round($totalAmount, 2),
                    "type" => "timekeeping"
                ]);
            } else {
                $isExisting = TimeKeepingPayment::where("adjustment_timekeeping_id", $timeKeeping->id)->first();
                if ($isExisting) {
                    $isExisting->delete();
                }

                TimeKeepingPayment::create([
                    "adjustment_timekeeping_id" => $timeKeeping->id,
                    "base_amount" => round($paymentPerDay,2),
                    "tardiness_deduction" => round($tardinessDeduction,2),
                    "undertime_deduction" => round($undertimeDeduction,2),
                    "regular_overtime" => round($regular_overtime,2),
                    "night_differential" => round($night_differential,2),
                    "night_differential_overtime" => round($night_differential_overtime,2),
                    "legal_holiday" => round($legal_holiday,2),
                    "special_holiday" => round($special_holiday,2),
                    "legal_holiday_overtime" => round($legal_holiday_overtime,2),
                    "special_holiday_overtime" => round($special_holiday_overtime,2),
                    "total_amount" => round($totalAmount,2),
                    "cancelled_off" => round($cancelled_off, 2),
                    "type" => "adjustment_timekeeping"
                ]);
            }
        }
    }

    public function getCutOff($date){
        $carbonDate = Carbon::parse($date);
        $day = $carbonDate->day;

        $cutOff1 = [11,12,13,14,15,16,17,18,19,20,21,22,23,24,25];
        $cutOff2 = [26,27,28,29,30,31,1,2,3,4,5,6,7,8,9,10];
        if (in_array($day, $cutOff2)) {
            if ($day <= 10) { 
                $startTime = $carbonDate->copy()->setDay(26)->subMonth()->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->endOfDay();
            } else {
                $startTime = $carbonDate->copy()->setDay(26)->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->addMonth()->endOfDay();
            }
        } else {
            $startTime = $carbonDate->copy()->setDay(11)->startOfDay();
            $endTime = $carbonDate->copy()->setDay(25)->endOfDay();
        }

        return [
            'start_time' => $startTime->toDateString(),
            'end_time' => $endTime->toDateString(),
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

    public function payHoliday($date, $employee, $cutOff) {
        $shouldPay = false;
        $holidayDate = Carbon::parse($date);
        $weekDateRange = $this->getCustomWeekRange($date, $cutOff);
        $schedules = ScheduleTime::where("employee_id", $employee->id)
        ->whereBetween("date", [$weekDateRange["start"], $weekDateRange["end"] ])
        ->get();

        //head office
        if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
            $hasLeaveOrSuspension = $schedules->contains(function ($schedule) {
                return in_array($schedule->timetable, ['LEAVE', 'SUSPENSION']);
            });
            $otherSchedules = $schedules->filter(function ($schedule) {
                if (!in_array($schedule->timetable, ["LEAVE", "SUSPENSION", "REST DAY"])) {
                    return $schedule;
                }
            });

            if ($hasLeaveOrSuspension) {
                $shouldPay = false;
            }
            $pay = true;
            foreach ($otherSchedules as $schedule) {
                $log = BiotimeLog::where("date", $schedule->date)
                ->where("employee_id", $employee->id)
                ->first();

                if (!$log && $schedule->timetable !== "NO SCHEDULE") {
                    $pay = false;
                }
            }

            if ($pay) {
                $shouldPay = true;
            }
        }

        //branch
        if ($employee->schedule->schedule_type->name === "Normal Schedule") {
            $previousDate = $holidayDate->copy()->subDay()->toDateString();
            $previousDateSchedule = ScheduleTime::where("date", $previousDate)->where("employee_id", $employee->id)->first();
            $hasLog = BiotimeLog::where("date", $previousDate)->where("employee_id", $employee->id)->first();

            if ($previousDateSchedule && $previousDateSchedule->timetable === "REST DAY") {
                $currentDate = Carbon::parse($previousDate);
                $previousDate = $currentDate->copy()->subDay()->toDateString();
                $previousDateSchedule = ScheduleTime::where("date", $previousDate)->where("employee_id", $employee->id)->first();
                $hasLog = BiotimeLog::where("date", $previousDate)->where("employee_id", $employee->id)->first();

                if ($hasLog) {
                    $shouldPay = true;
                } else {
                    $shouldPay = false;
                }

            } else if ($previousDateSchedule && $previousDateSchedule->timetable === "INCENTIVE LEAVE") {
                $shouldPay = true;
            } else {
                if ($hasLog) {
                    $shouldPay = true;
                } else {
                    $shouldPay = false;
                }
            }
        }

        return $shouldPay;
    }

    private function getCustomWeekRange($date, $cutOff)
    {
        $holidayDate = Carbon::parse($date);
        $startOfWeek = $holidayDate->copy()->startOfWeek(CarbonInterface::MONDAY);
        $endOfWeek = $holidayDate->copy()->endOfWeek(CarbonInterface::SUNDAY);

        $startDate = Carbon::parse($cutOff["start_time"]);
        $endDate = Carbon::parse($cutOff["end_time"]);

        if ($startOfWeek->lt($startDate)) {
            $startOfWeek = $startDate;
        }
        if ($endOfWeek->gt($endDate)) {
            $endOfWeek = $endDate;
        }

        return [
            "start" => $startOfWeek,
            "end" => $endOfWeek
        ];
    }

    public function isCompleteAttendanceThisCutOff($employee, $cutOff) {
        $isComplete = true;

        $schedules = ScheduleTIme::whereBetween("date", [$cutOff["start_time"], $cutOff["end_time"]])
        ->where("employee_id",$employee->id)
        ->get();

        //has leave or suspension
        $hasLeaveOrSuspension = $schedules->contains(function ($schedule) {
            return in_array($schedule->timetable, ['LEAVE', 'SUSPENSION']);
        });

        $otherSchedules = $schedules->filter(function ($schedule) {
            if (!in_array($schedule->timetable, ["LEAVE", "SUSPENSION", "REST DAY", "NO SCHEDULE"])) {
                return $schedule;
            }
        });
        
        if ($hasLeaveOrSuspension) {
            $isComplete = false;
        }

        foreach($otherSchedules as $schedule) {
            $timeKeeping = TimeKeeping::where("date", $schedule->date)
            ->where("employee_id", $employee->id)
            ->first();

            if (!$timeKeeping) {
                $isComplete = false;
            }
        }

        return $isComplete;
    }
}
