<?php

namespace App\Http\Services;

use App\Models\BiotimeLog;
use App\Models\BiotimePunch;
use App\Models\Cutoff;
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
use Exception;
use Illuminate\Support\Facades\Http;

class TimeKeepingService
{
    public function computeTimeKeeping(
        $branchId,
        $log,
        $biotimePunches,
        $actualSchedule,
        $employee,
        Model $model,
        $isAdjustment = false,
        $paymentType = "timekeeping"
    ){
        $timeKeeping = null;

        $scheduleTime = ScheduleTime::where("timetable", $actualSchedule)
        ->where("employee_id", $employee->id)
        ->where("date", $log->date)
        ->first();

        if (!$scheduleTime) {
            $scheduleTime = ScheduleTime::where("employee_id", $employee->id)
            ->where("date", $log->date)
            ->first();
        }

        $schedule = null;

        if ($isAdjustment) {
            $schedule = ScheduleMasterlist::where("selection", $actualSchedule)->first();
        } else {
            $schedule = ScheduleMasterlist::where("selection", $actualSchedule->timetable)->first();

            if (!$schedule) {
                $schedule = ScheduleMasterlist::where("start_time", $actualSchedule->check_in)
                ->where("end_time", $actualSchedule->check_out)
                ->first();
            }
        }

        // Log::info("==================");
        // Log::info($employee->full_name);
        // Log::info($log->date);
        // Log::info($schedule->selection);

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

        $hours = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;
        $totalDurationHOurs = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 10.5 : 9;
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

        if (
            strtolower($schedule->selection) === "flex" ||
            strtolower($schedule->selection) === "flex 1" ||
            strtolower($schedule->selection) === "flex 2"
        ) {
            $overtime = ($decimalHours - $totalDurationHOurs >= 1) ? $decimalHours - $totalDurationHOurs : 0;
        }

        // Log::info("OVERTIME: ". $overtime);

        $holiday = Holiday::whereHas("branches", function($query) use ($branchId) {
            $query->where("branches.id", $branchId);
        })->where("date", $log->date)->first();

        $shouldBeHalfDay = false;
        if (
            $tardiness >= 120 &&
            $schedule->selection !== "Spot Audit" &&
            $schedule->selection !== "MEI 08:00 TO 06:30" &&
            $schedule->selection !== "MEI 09:00 TO 19:30" &&
            $schedule->selection !== "Flex 1" &&
            $schedule->selection !== "Flex 2" &&
            strtolower($schedule->selection) !== "flex"
        ) {
            $workHours = $hours / 2;
            $tardiness = 0;
            $shouldBeHalfDay = true;
        }

        //undertime computation
        if ($workHours < $hours && $parsedLastPunch->isBefore($scheduleEnd) && $workHours >= ($hours / 2)) {
            $undertime = $parsedLastPunch->diffInMinutes($scheduleEnd, true);
        }
        if ($workHours < ($hours / 2)) {
            $halfScheduleTime = $scheduleStart->copy()->addMinutes($scheduleStart->diffInMinutes($scheduleEnd) / 2);
            $undertime = $parsedLastPunch->diffInMinutes($halfScheduleTime, true);
        }
        if ($workHours > $hours && $parsedLastPunch->isBefore($scheduleEnd)) {
            $undertime = $parsedLastPunch->diffInMinutes($scheduleEnd, true);
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
                if ($totalHolidayWorkedHours < $hours) {
                    $specialHolidayHours = $hours; 
                }
                $specialHolidayOvertime = $overtime;
            }

            $overtime = 0;
        }

        if (
            $biotimePunches->count() === 4 &&
            (
                $schedule->selection !== "Spot Audit" &&
                $schedule->selection !== "MEI 08:00 TO 06:30" &&
                $schedule->selection !== "MEI 09:00 TO 19:30" &&
                $schedule->selection !== "Flex 1" &&
                $schedule->selection !== "Flex 2" &&
                strtolower($schedule->selection) !== "flex"
            )
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
            (
                $schedule->selection !== "Spot Audit" &&
                $schedule->selection !== "MEI 08:00 TO 06:30" &&
                $schedule->selection !== "MEI 09:00 TO 19:30" &&
                $schedule->selection !== "Flex 1" &&
                $schedule->selection !== "Flex 2" &&
                strtolower($schedule->selection) !== "flex"
            )
        ) {
            $secondPunch = $biotimePunches[1];
            $parsedSecondPunch = Carbon::parse($secondPunch->punch_time)->seconds(0);

            $breakOutGap = $scheduleStart->diffInMinutes($parsedSecondPunch);
            $breakInGap = $parsedSecondPunch->diffInMinutes($scheduleEnd);

            // NO CLOCK IN
            if (
                $scheduleStart->diffInHours($parsedFirstPunch) > 3 &&
                $parsedFirstPunch->diffInHours($parsedSecondPunch) <= 2 &&
                $workHours < $schedule->total_hours
            ) {
                $tardiness = $scheduleStart->diffInMinutes($parsedFirstPunch);
            }

            // NO BREAK OUT
            if (
                $scheduleStart->diffInHours($parsedFirstPunch) <= 3 &&
                $parsedFirstPunch->diffInHours($parsedSecondPunch) > 3 &&
                $scheduleEnd->diffInHours($parsedLastPunch) < 3 &&
                $breakOutGap > $breakInGap &&
                $workHours >= $schedule->total_hours
            ) {
                $tardiness = $scheduleStart->diffInMinutes($parsedSecondPunch) - $scheduledBreakInMinutes;
            }

            // NO BREAK IN
            if (
                $parsedSecondPunch->diffInHours($parsedLastPunch) > 3 &&
                $parsedSecondPunch->diffInMinutes($parsedLastPunch) > $scheduledBreakInMinutes &&
                $scheduleEnd->diffInHours($parsedLastPunch) < 3 &&
                $breakInGap > $breakOutGap &&
                $workHours >= $schedule->total_hours
            ) {
                $tardiness = $parsedSecondPunch->diffInMinutes($scheduleEnd) - $scheduledBreakInMinutes;
            }

            // NO CLOCK OUT
            if (
                $scheduleStart->diffInHours($parsedFirstPunch) <= 3 &&
                $parsedSecondPunch->diffInHours($parsedLastPunch) < 3 &&
                $workHours < $schedule->total_hours
            ) {
                $underTimeInMinutes = $scheduleEnd->diffInMinutes($parsedLastPunch);
                $undertime = $underTimeInMinutes;
            }
        }

        if (
            $biotimePunches->count() === 2 &&
            (
                $schedule->selection !== "Spot Audit" &&
                $schedule->selection !== "MEI 08:00 TO 06:30" &&
                $schedule->selection !== "MEI 09:00 TO 19:30" &&
                $schedule->selection !== "Flex 1" &&
                $schedule->selection !== "Flex 2" &&
                strtolower($schedule->selection) !== "flex"
            )
        ) {
            if ($workHours >= 4 && $workHours <= 5) {
                $workHours = $schedule->total_hours / 2;
            } else if ($workHours >= 6 && $parsedLastPunch->lt($scheduleEnd)) {
                $undertime = $scheduleEnd->diffInMinutes($parsedLastPunch);
            }
        }

        if (
            $biotimePunches->count() > 4 &&
            (
                $schedule->selection !== "Spot Audit" &&
                $schedule->selection !== "MEI 08:00 TO 06:30" &&
                $schedule->selection !== "MEI 09:00 TO 19:30" &&
                $schedule->selection !== "Flex 1" &&
                $schedule->selection !== "Flex 2" &&
                strtolower($schedule->selection) !== "flex"
            )
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
                $tardiness = $scheduleStart->diffInMinutes($parsedFirstPunch);
            }
        }

        $tenPM = Carbon::parse($log->date." ".'22:00:00');
        $newDate = Carbon::parse($log->date)->addDay()->format('Y-m-d');
        $sixAM = Carbon::parse($newDate." ".'06:00:00');
        //FLEX TO BE CHANGED
        if (
            $scheduleEnd->greaterThan($tenPM) &&
            $parsedLastPunch->greaterThan($tenPM) &&
            (
                $schedule->selection !== "Spot Audit" &&
                $schedule->selection !== "MEI 08:00 TO 06:30" &&
                $schedule->selection !== "MEI 09:00 TO 19:30" &&
                $schedule->selection !== "Flex 1" &&
                $schedule->selection !== "Flex 2" &&
                strtolower($schedule->selection) !== "flex"
            )
        ) {
            $differ = $scheduleEnd;
            if ($parsedLastPunch->isBefore($scheduleEnd)) {
                $differ = $parsedLastPunch;
            }

            $nightDiffSeconds = $differ->diffInSeconds($tenPM);
            if ($differ->greaterThan($sixAM)) {
                $nightDiffSeconds = $tenPM->diffInSeconds($sixAM);
            }

            $nightDiffHours = $nightDiffSeconds / 3600;
            if ($nightDiffHours < 1) {
                $nightDiffHours = 0;
            }
            
            if ($overtime >= 1) {
                if ($parsedLastPunch->greaterThan($sixAM)) {
                    $diffSeconds = $differ->diffInSeconds($sixAM);
                    $nightDiffOt = $diffSeconds / 3600;
                } else {
                    $nightDiffOt = $overtime;
                }
            }
        }

        if (
            $tenPM->greaterThanOrEqualTo($scheduleEnd) &&
            $parsedLastPunch->greaterThan($tenPM) &&
            strtolower($schedule->selection) === "flex"
        ) {
            $differ = $scheduleEnd;
            $nightDiffSeconds = $differ->diffInSeconds($tenPM);
            if ($differ->greaterThan($sixAM)) { 
                $nightDiffSeconds = $tenPM->diffInSeconds($sixAM);
            }
            
            if ($overtime >= 1) {
                if ($parsedLastPunch->greaterThan($sixAM)) {
                    $diffSeconds = $differ->diffInSeconds($sixAM);
                    $nightDiffOt = $diffSeconds / 3600;
                } else {
                    $nightDiffOt = $overtime;
                }
            }
        }

        //FLEX TO BE CHANGED
        if (
            $tenPM->greaterThanOrEqualTo($scheduleEnd) &&
            $parsedLastPunch->greaterThan($tenPM) &&
            (
                $schedule->selection !== "Spot Audit" &&
                $schedule->selection !== "MEI 08:00 TO 06:30" &&
                $schedule->selection !== "MEI 09:00 TO 19:30" &&
                $schedule->selection !== "Flex 1" &&
                $schedule->selection !== "Flex 2" &&
                strtolower($schedule->selection) !== "flex"
            )
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
            $undertime = 0;
            if ($schedule->selection == "NO SCHEDULE") {
                $workHours = 0;
                $overtime = $biotimePunches->count() > 3 ? $decimalHours - 1 : $decimalHours;
            } else {
                $overtime = ($decimalHours - $totalDurationHOurs >= 1) ? $decimalHours - $totalDurationHOurs : 0;
            }

            if (strtolower($schedule->selection) === "flex" && $workHours < $hours) {
                $shortMins = ($hours - $workHours) * 60;
                $toDeduct = fmod($shortMins, 1);

                $undertime = $shortMins - $toDeduct;
            }

            if ($parsedLastPunch->greaterThan($tenPM)) {
                $flexDiffInSeconds = $parsedFirstPunch->diffInSeconds($tenPM);
                $flexiHours = $flexDiffInSeconds / 3600;

                if ($flexiHours > $totalDurationHOurs) {
                    $flexOTDiffInSeconds = $parsedLastPunch->diffInSeconds($tenPM);
                    $flexiOTHours = $flexOTDiffInSeconds / 3600;
                    $nightDiffOt = $flexiOTHours;
                } else {
                    if (strtolower($schedule->selection) === "flex") {
                        $flexNightDiffInSeconds = $parsedLastPunch->diffInSeconds($tenPM);
                        $flexNightDiff = $flexNightDiffInSeconds / 3600;
                        $nightDiffHours = $flexNightDiff;
                    }
                }
            }
        }

        //undertime 240 or 285 conversion to half day
        if ($employee->schedule->schedule_type->name === "Compressed Schedule" && $undertime >= 285) {
            $undertime = $undertime - 285;
            $shouldBeHalfDay = true;
        } else if ($employee->schedule->schedule_type->name === "Normal Schedule" && $undertime >= 240) {
            $undertime = $undertime - 240;
            $shouldBeHalfDay = true;
        }

        if (
            $schedule->selection === "Spot Audit" ||
            $schedule->selection === "MEI 08:00 TO 06:30" ||
            $schedule->selection === "MEI 09:00 TO 19:30" ||
            $schedule->selection === "Flex 1" ||
            $schedule->selection === "Flex 2"
        ) {
            $punches = $biotimePunches;
            $firstPunch = $punches[0];

            $punchWithTravelTime = $punches->first(function ($punch) {
                return $punch->branch && $punch->branch->travel_time;
            });

            if (($schedule->selection === "Spot Audit" ||
                $schedule->selection === "MEI 08:00 TO 06:30" ||
                $schedule->selection === "MEI 09:00 TO 19:30") && 
                $punchWithTravelTime 
            ) {
                if ($firstPunch->branch_id !== null) {
                    $travel_time += (float)$punchWithTravelTime->branch->travel_time;
                }
                if ($lastPunch->branch_id !== null) {
                    $travel_time += (float)$punchWithTravelTime->branch->travel_time;
                }
                if ($firstPunch->branch_id === null && $lastPunch->branch_id === null ) {
                    $travel_time = 0;
                }
            }

            $workHours += (int)$travel_time;
            $undertime = 0;
            $tardiness = 0;
            $shouldBeHalfDay = false;
            $totalWorkedHours = $workHours - ($scheduledBreakInMinutes / 60);

            if ($totalWorkedHours < 9.5) {
                $undertime = (10.5 - $totalWorkedHours) * 60;
            }

            if ($totalWorkedHours < 10.5 && $totalWorkedHours >= 9.5 ) {
                $totalWorkedHours = 9.5;
            }

            if ($workHours > 10.5) {
                $overtime = $workHours - 10.5 < 1 ? 0 : $workHours - 10.5;
            }

            if ($holiday && $legalHoliday === "Yes") {
                $legalHolidayHours = $totalHolidayWorkedHours;
                $legalHolidayOvertime = $overtime;
                $overtime = 0;
            }

            if ($holiday && $specialHoliday === "Yes") {
                $specialHolidayHours = $totalHolidayWorkedHours;
                $specialHolidayOvertime = $overtime;
                $overtime = 0;
            }
        }

        if ($biotimePunches->count() > 1) {

            $cutOff = $this->getCutOff($scheduleTime->date);        
            $activeCutOff = CutOff::where("start_date", $cutOff['start_time'])->where("end_date", $cutOff["end_time"])->first();

            $overtimeApplied = Overtime::where("employee_id", $employee->id)
            ->where("overtime_date", $log->date)
            ->whereNot("status", "Voided")
            ->first();

            $generalOvertime = $overtime;
            $approvedOvertime = 0;
            $remarks = null;
            if ($legalHoliday === "Yes") {
                $generalOvertime = $legalHolidayOvertime;
            }
            if ($specialHoliday === "Yes") {
                $generalOvertime = $specialHolidayOvertime;
            }

            Log::info("GEN OVERTIME: ". $generalOvertime);

            if ($overtimeApplied && $generalOvertime >= 1) {
                if ($overtimeApplied->total_hours <= $generalOvertime) {
                    $approvedOvertime = $overtimeApplied->total_hours;
                } else {
                    $approvedOvertime = $generalOvertime;
                    $remarks = "Approved overtime is higher than the computed overtime from the system.";
                }

                $currentCutOff = $this->getCurrentCutOff();

                $totalHoursPaid = $approvedOvertime;

                if ($overtimeApplied->hours_paid > 0 && $isAdjustment) {
                    if ($overtimeApplied->total_hours < ($overtimeApplied->hours_paid + $approvedOvertime)) {
                        $totalHoursPaid = $overtimeApplied->total_hours;
                    } else {
                        $totalHoursPaid += $overtimeApplied->hours_paid;
                    }
                }

                $cutOffPaid = Carbon::parse($cutOff["start_time"])->format("m/d/Y")." TO ". Carbon::parse($cutOff['end_time'])->format("m/d/Y");
                
                if ($isAdjustment) {
                    $cutOffPaid = Carbon::parse($currentCutOff["start_time"])->format("m/d/Y")." TO ". Carbon::parse($currentCutOff['end_time'])->format("m/d/Y");
                    $activeCutOff = Cutoff::where("status", "active")->first();

                    if ($activeCutOff) {
                        $cutOffPaid = Carbon::parse($activeCutOff->start_date)->format("m/d/Y") ." TO ". Carbon::parse($activeCutOff->end_date)->format("m/d/Y");
                    }
                }

                $remainingHours = $overtimeApplied->total_hours - $totalHoursPaid;
                $payrollStatus = $remainingHours >= 1 ? "unpaid" : "paid";

                $overtimeApplied->update([
                    "hours_paid" => $totalHoursPaid,
                    "cutoff_paid" => $cutOffPaid,
                    "payment_type" => $paymentType,
                    "status" => $remainingHours >= 1 ? "Partialy Paid" : "Paid"
                ]);

                // if (!empty($overtimeApplied->cshub_overtime_id)) {
                //     $overtimeLink = env('CS_HUB_URL_LINK')."/update-overtime/".$overtimeApplied->cshub_overtime_id;
                //     $user = env('CS_HUB_ADMIN_EMAIL');
                //     $password = env('CS_HUB_ADMIN_PASSWORD');

                //     try {
                //         Http::withBasicAuth($user, $password)
                //         ->withHeaders([
                //             "Accept" => "application/json",
                //             "Content-Type" => "application/json"
                //         ])->timeout(60)
                //         ->retry(50, 1000, function ($exception, $attempt) {
                //             return str_contains($exception->getMessage(), 'cURL error 60');
                //         })
                //         ->post($overtimeLink,[
                //             "status_payroll" => $payrollStatus,
                //             "status" => "approved",
                //         ]);
                //     } catch (Exception $e) {
                //         // Log::info("CSHub overtime api error encountered: ".$e->getMessage());
                //     }
                // }
            }

            $isExisting = $model->where("employee_id", $employee->id)
                ->where("date" , $log->date)
                ->get();

            $manualOverride = null;
            if ($isExisting && $isExisting->count() > 0) {
                $overrideRow = $isExisting->first(function ($row) {
                    return (bool) $row->is_manual_override;
                });
                if ($overrideRow) {
                    $manualOverride = [
                        "tag" => $overrideRow->manual_tag,
                        "undertime_minutes" => (int) ($overrideRow->manual_undertime_minutes ?? 0),
                        "tardiness_minutes" => (int) ($overrideRow->manual_tardiness_minutes ?? 0),
                    ];
                }

                foreach ($isExisting as $t) {
                    $payment = TimeKeepingPayment::where("timekeeping_id", $t->id)->first();
                    if ($payment) {
                        $payment->delete();
                    }
                    $t->delete();
                }
            }

            // Log::info("UNPROCESSED: ".$finalWorkHours);
            // Log::info("WORKED HOURS: ".$workHours);
            // Log::info("SHOULD BE HALFDAY: ".$shouldBeHalfDay);
            if (
                ($workHours >= $schedule->total_hours - 3.5 && $undertime < 240 ) &&
                !$isCancelledOff &&
                !$shouldBeHalfDay
            ) {
                $finalWorkHours = $hours;
                // Log::info("CONDITION 1");
            } else if (
                $workHours >= 4 &&
                $undertime >= 240 &&
                !$isCancelledOff &&
                $workHours > 0
            ) {
                $finalWorkHours = $hours;
                // Log::info("CONDITION 2");
            } else if (
                $workHours >= $schedule->total_hours / 2 &&
                $undertime <= 240 &&
                !$shouldBeHalfDay
            ) {
                $finalWorkHours = $hours;
                // Log::info("CONDITION 3");
            } else if (
                (($workHours < $schedule->total_hours - 3.5 || $workHours >= $schedule->total_hours / 2) || $shouldBeHalfDay) &&
                !$isCancelledOff &&
                $workHours > 0 &&
                $undertime < 120
            ) {
                $finalWorkHours = $hours / 2;
                // Log::info("CONDITION 4");
            }


            // Log::info("PROCESSED: ".$finalWorkHours);

            if ($holiday) {
                if ($legalHoliday === "Yes") {
                    $legalHolidayHours = $hours;
                } else if ($specialHoliday === "Yes") {
                    $specialHolidayHours = $hours;
                }
            }

            if ($schedule->selection === "REST DAY") {
                $isCancelledOff = true;
                $overtime = 0;
                $legalHolidayOvertime = 0;
                $specialHolidayOvertime = 0;
                $tardiness = 0;
                $nightDiffOt = 0;
                $nightDiffHours = 0;
                $cancelled_off_hours = round($decimalHours, 2);
            }

            if ($workHours <= 1 && !$isCancelledOff && $schedule->selection !== "NO SCHEDULE") {
                $finalWorkHours = 0;
                $legalHoliday = 0;
                $specialHoliday = 0;
                $overtime = 0;
                $tardiness = 0;
                $nightDiffHours = 0;
                $nightDiffOt = 0;
                $legalHolidayHours = 0;
                $legalHolidayOvertime = 0;
                $specialHolidayHours = 0;
                $specialHolidayOvertime = 0;
                $undertime = 0;
                $approvedOvertime = 0;
                $cancelled_off_hours = 0;
            }

            $timeKeeping = $model->create([
                "employee_id" => $employee->id,
                "biometric_number" => $employee->biometric_number,
                "biotime_log_id" => $log->id,
                "schedule_time_id" => $scheduleTime->id ?? 0,
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
                "cancelled_off_hours" => $this->formatHours($cancelled_off_hours),
            ]);

            if ($manualOverride) {
                $hoursPerDay = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;
                $finalOverrideHours = in_array($manualOverride["tag"], ["halfday", "tardiness_halfday"], true)
                    ? $hoursPerDay / 2
                    : $hoursPerDay;

                $updateData = [
                    "final_worked_hours" => $finalOverrideHours,
                    "is_manual_override" => true,
                    "manual_tag" => $manualOverride["tag"],
                    "manual_undertime_minutes" => null,
                    "manual_tardiness_minutes" => null,
                ];

                if (in_array($manualOverride["tag"], ["undertime", "halfday", "tardiness_undertime"], true)) {
                    $updateData["undertime"] = $manualOverride["undertime_minutes"];
                    $updateData["manual_undertime_minutes"] = $manualOverride["undertime_minutes"];
                }

                if (in_array($manualOverride["tag"], ["tardiness", "tardiness_undertime", "tardiness_halfday"], true)) {
                    $updateData["tardiness"] = $manualOverride["tardiness_minutes"];
                    $updateData["manual_tardiness_minutes"] = $manualOverride["tardiness_minutes"];
                }

                $timeKeeping->update($updateData);
            }

            if ($overtimeApplied && $approvedOvertime >= 1) {
                $overtimeApplied->update([
                    "timekeeping_id" => !$isAdjustment ? $timeKeeping->id : null,
                    "adjustment_timekeeping_id" => $isAdjustment ? $timeKeeping->id : null
                ]);
            }
        }

        if ($schedule->selection === "INCENTIVE LEAVE") {
            $timeKeeping = $model->create([
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
        
        return $timeKeeping;
    }

    public function computePayment($timeKeeping, $actualSchedule, $isAdjustment = false) {
        $timekeepingPayment = null;
        if ($timeKeeping) {
            $employee = $timeKeeping->employee;

            $date = $timeKeeping->date;

            $schedule = null;

            if ($isAdjustment) {
                $schedule = ScheduleMasterlist::where("selection", $actualSchedule)->first();
            } else {
                $schedule = ScheduleMasterlist::where("selection", $actualSchedule->timetable)->first();

                if (!$schedule) {
                    $schedule = ScheduleMasterlist::where("start_time", $actualSchedule->check_in)
                    ->where("end_time", $actualSchedule->check_out)
                    ->first();
                }
            }

            // $scheduledBreak = Carbon::parse($date . ' ' . $schedule->time_break ?? "00:00");
            // $scheduledBreakInHours = $scheduledBreak->hour + ($scheduledBreak->minute / 60);             
            // $totalDutyHours = $schedule->total_hours - $this->formatHours($scheduledBreakInHours);
            
            $paymentPerDay = $employee->dailySalary();
            $paymentPerHour =  $employee->schedule->schedule_type->name === "Compressed Schedule" ? $paymentPerDay / 9.5 : $paymentPerDay / 8;
            $paymentPerMinute = $paymentPerHour / 60;

            if (
                ($employee->schedule->schedule_type->name === "Normal Schedule" && $timeKeeping->final_worked_hours == 4) ||
                ($employee->schedule->schedule_type->name === "Compressed Schedule" && $timeKeeping->final_worked_hours == 4.75)
            ) {
                $paymentPerDay = $employee->dailySalary() / 2;
            }

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
            $night_differential = $timeKeeping->night_diff_hours >= 1 ? $timeKeeping->night_diff_hours * ($paymentPerHour * .1) : 0;

            if ($employee->current_branch->type === "Branch") {
                $timeKeeping->regular_overtime = floor($timeKeeping->regular_overtime);
                $timeKeeping->legal_holiday_overtime = floor($timeKeeping->legal_holiday_overtime);
                $timeKeeping->special_holiday_overtime = floor($timeKeeping->special_holiday_overtime);
                $timeKeeping->night_diff_overtime = floor($timeKeeping->night_diff_overtime);
            }

            if ($timeKeeping->regular_overtime > 0) {
                if ($timeKeeping->regular_overtime < $timeKeeping->approved_overtime) {
                    $regular_overtime = $timeKeeping->regular_overtime * ($paymentPerHour * 1.25);
                } else {
                    $regular_overtime = $timeKeeping->approved_overtime * ($paymentPerHour * 1.25);
                }
            }
            
            if ($timeKeeping->night_diff_overtime > 0) {
                if ($timeKeeping->night_diff_overtime < $timeKeeping->approved_overtime) {
                    $night_differential_overtime = $timeKeeping->night_diff_overtime > 1 ? $timeKeeping->night_diff_overtime * ($paymentPerHour * .125) : 0;
                } else {
                    $night_differential_overtime = $timeKeeping->approved_overtime * ($paymentPerHour * .125);
                }
            }

            if ($timeKeeping->legal_holiday_overtime > 0) {
                if ($timeKeeping->legal_holiday_overtime <= $timeKeeping->approved_overtime) {
                    $legal_holiday_overtime = $timeKeeping->legal_holiday_overtime * ($paymentPerHour * 2.6);
                } else {
                    $legal_holiday_overtime = $timeKeeping->approved_overtime * ($paymentPerHour * 2.6);
                }
            }

            if ($timeKeeping->special_holiday_overtime > 0) {
                if ($timeKeeping->special_holiday_overtime <= $timeKeeping->approved_overtime) {
                    $special_holiday_overtime = $timeKeeping->special_holiday_overtime * ($paymentPerHour * 1.69);
                } else {
                    $special_holiday_overtime = $timeKeeping->approved_overtime * ($paymentPerHour * 1.69);
                }
            }

            $totalAmount = ($paymentPerDay + $regular_overtime + $night_differential + $night_differential_overtime + $legal_holiday + $special_holiday + $legal_holiday_overtime + $special_holiday_overtime + $cancelled_off)
                - ($tardinessDeduction + $undertimeDeduction);

            if ($timeKeeping->is_cancelled_off) {
                $cancelledOffWorkedHours = $timeKeeping->cancelled_off_hours > $timeKeeping->approved_overtime ? $timeKeeping->cancelled_off_hours : $timeKeeping->approved_overtime;

                if ($employee->schedule->schedule_type->name === "Normal Schedule") {
                    $cancelledOffWorkedHours = $timeKeeping->cancelled_off_hours < 8 ? $timeKeeping->cancelled_off_hours : 8;
                } else {
                    $cancelledOffWorkedHours = $timeKeeping->cancelled_off_hours < 9.5 ? $timeKeeping->cancelled_off_hours : 9.5;
                }

                $cancelled_off = $cancelledOffWorkedHours * ($paymentPerHour * 1.3);

                // if ($timeKeeping->legal_holiday === "Yes") {
                //     $cancelled_off = $cancelledOffWorkedHours * ($paymentPerHour * 2.6);
                // }

                $totalAmount = $cancelled_off;
            }

            if (!$isAdjustment) {
                $payment = TimeKeepingPayment::where("timekeeping_id", $timeKeeping->id)->first();
                if ($payment) {
                    $payment->delete();
                }
                
                $biotimeLog = BiotimeLog::where("employee_id", $employee->id)
                ->where("date", $date)
                ->first();

                if (!$biotimeLog || $biotimeLog->punches()->count() == 0) {
                    $totalAmount = 0;
                }

                $timekeepingPayment = TimeKeepingPayment::create([
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

                $timekeepingPayment = TimeKeepingPayment::create([
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

            return $timekeepingPayment;
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

    public function payHoliday($date, $employee, $cutOff, ?Holiday $holiday = null) {
        $shouldPay = true;
        $holidayDate = Carbon::parse($date);
        $hasHolidayLogs = BiotimeLog::where("date", $date)
        ->where("employee_id", $employee->id)
        ->first();
        $weekDateRange = $this->getCustomWeekRange($date, $cutOff, $employee);
        $schedules = ScheduleTime::with(["timekeeping", "timekeeping.biotime_log"])
        ->whereBetween("date", [$weekDateRange["start"], $weekDateRange["end"] ])
        ->where("employee_id", $employee->id)
        ->get();

        //head office
        if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
            $weekAttendance = 0;
            $hasLeaveOrSuspension = $schedules->contains(function ($schedule) {
                return in_array($schedule->timetable, ['LEAVE', 'SUSPENSION']);
            });
            $otherSchedules = $schedules->filter(function ($schedule) {
                if (!in_array($schedule->timetable, ["LEAVE", "SUSPENSION", "REST DAY"])) {
                    return $schedule;
                }
            });
            $noSchedules = $schedules->filter(function ($schedule) {
                if (in_array($schedule->timetable, ["NO SCHEDULE"])) {
                    return $schedule;
                }
            });

            foreach ($otherSchedules as $schedule) {
                $log = $schedule->timekeeping->biotime_log ?? null;

                if (!$log) {
                    $log = BiotimeLog::where("employee_id", $schedule->employee_id)
                    ->where("date", $schedule->date)
                    ->first();
                }

                if (!$log && $schedule->timetable !== "NO SCHEDULE") {
                    $shouldPay = false;
                }
            }

            if ($hasLeaveOrSuspension) {
                $shouldPay = false;
            }

            if ($holiday && $noSchedules->count() > 1 && $holiday->required_days >= 5) {
                $noSchedWithoutLogs = 0;
                foreach ($noSchedules as $noSched) {
                    $noSchedLogs = BiotimeLog::where("date", $noSched->date)
                    ->where("employee_id", $employee->id)
                    ->first();

                    if (!$noSchedLogs) {
                        $noSchedWithoutLogs += 1;
                    } else {
                        $weekAttendance += 1;
                    }
                }
                if ($noSchedWithoutLogs > 1) {
                    $shouldPay = false;
                }
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

                if (!$hasLog) {
                    $shouldPay = false;
                }

            } else if ($previousDateSchedule && $previousDateSchedule->timetable === "INCENTIVE LEAVE") {
                $shouldPay = true;
            } else if ($previousDateSchedule && $previousDateSchedule->timetable === "NO SCHEDULE") {
                $shouldPay = true;
            } else {
                if (!$hasLog) {
                    $shouldPay = false;
                }
            }

            if ($employee->current_position->timekeeping_type === "Daily" && $hasHolidayLogs) {
                $shouldPay = true;
            }
        }

        return [
            "should_pay" => $shouldPay,
            "week_attendance" => $weekAttendance
        ];
    }

    private function getCustomWeekRange($date, $cutOff, $employee)
    {
        $holidayDate = Carbon::parse($date);
        $startOfWeek = $holidayDate->copy()->startOfWeek(CarbonInterface::MONDAY);
        $endOfWeek = $holidayDate->copy()->endOfWeek(CarbonInterface::SUNDAY);

        $startDate = Carbon::parse($cutOff["start_time"]);
        $endDate = Carbon::parse($cutOff["end_time"]);

        if ($endOfWeek->gt($endDate)) {
            $newStart = Carbon::parse($endDate)
            ->addDay()
            ->format("Y-m-d");

            $timeKeepings = TimeKeeping::where("employee_id", $employee->id)
            ->whereBetween("date", [$newStart, $endOfWeek->format("Y-m-d")])
            ->get();

            if ($timeKeepings->count() === 0) {
                $endOfWeek = $endDate;
            }

        }

        return [
            "start" => $startOfWeek->format("Y-m-d"),
            "end" => $endOfWeek->format("Y-m-d")
        ];
    }

    public function getWeekRange($date)
    {
        $selected_date = Carbon::parse($date);
        $startOfWeek = $selected_date->copy()->startOfWeek(CarbonInterface::MONDAY);
        $endOfWeek = $selected_date->copy()->endOfWeek(CarbonInterface::SUNDAY);

        return [
            "start" => $startOfWeek->format("Y-m-d"),
            "end" => $endOfWeek->format("Y-m-d")
        ];
    }

    public function isCompleteAttendanceThisCutOff($employee, $schedules) {
        $isComplete = true;

        //has leave or suspension
        $hasLeaveOrSuspension = $schedules->contains(function ($schedule) {
            return in_array($schedule->timetable, ['LEAVE', 'SUSPENSION']);
        });

        $otherSchedules = $schedules->filter(function ($schedule) use ($employee) {
            if (!in_array($schedule->timetable, ["REST DAY", "NO SCHEDULE", "INCENTIVE LEAVE"])) {
                return $schedule;
            }
        });

        $restDays = $schedules->filter(function ($schedule) use ($employee) {
            if (in_array($schedule->timetable, ["REST DAY"])) {
                return $schedule;
            }
        });

        $hasNoSchedule = $schedules->contains(function ($schedule) {
            return in_array($schedule->timetable, ['NO SCHEDULE']);
        });
        
        if ($hasLeaveOrSuspension) {
            $isComplete = false;
        }

        if (
            $employee->schedule->schedule_type->name === "Normal Schedule" &&
            $hasNoSchedule
        ) {
            $isComplete = false;
        }

        if (
            $employee->schedule->schedule_type->name === "Normal Schedule" &&
            $restDays->count() > 2
        ) {
            $isComplete = false;
        }

        foreach($otherSchedules as $schedule) {
            $timeKeeping = $schedule->timekeeping;
            $biotimeLog = $timeKeeping->biotime_log ?? null;

            if (!$timeKeeping || !$biotimeLog) {
                $isComplete = false;
            }
        }

        return $isComplete;
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

    public function deductPreviousPaidHoliday($currentCutOff, $employee) {
        $deductSpecial = 0;
        $deductLegal = 0;
        $deductSpecialOt = 0;
        $deductLegalOt = 0;
        $dates = [];

        $weekRange = $this->getPreviousCutOff($currentCutOff["start_time"]);

        $timeKeepings = TimeKeeping::where("employee_id", $employee->id)
        ->where(function ($query) {
            $query->where("legal_holiday", "Yes")
                ->orWhere("special_holiday", "Yes");
        })
        ->where("paid_holiday", "Yes")
        ->whereBetween("date", [$weekRange['start_time'], $weekRange['end_time']])
        ->get();

        if ($timeKeepings->count() > 0) {
            foreach ($timeKeepings as $timeKeeping) {
                $holiday = Holiday::where("date", $timeKeeping->date)
                ->whereHas("branches", function ($query) use ($timeKeeping) {
                    $query->where("branch_id", $timeKeeping->branch_id);
                })->first();
                $cutOff = $this->getCutOff($timeKeeping->date);
                $shouldBePaid = $this->payHoliday($timeKeeping->date, $employee, $cutOff, $holiday);
    
                if (!$shouldBePaid['should_pay']) {
                    if ($timeKeeping->legal_holiday === "Yes") {
                        $deductLegal += $timeKeeping->legal_holiday_hours;
                        $deductLegalOt += $timeKeeping->legal_holiday_overtime;
                    }
        
                    if ($timeKeeping->special_holiday === "Yes") {
                        $deductSpecial += $timeKeeping->special_holiday_hours;
                        $deductSpecialOt += $timeKeeping->special_holiday_overtime;
                    }
                    $dates[] = $timeKeeping->date;
                }
    
            }
        }

        return [
            "special_holiday_hours_deduction" => $deductSpecial,
            "special_holiday_overtime_deduction" => $deductSpecialOt,
            "legal_holiday_hours_deduction" => $deductLegal,
            "legal_holiday_overtime_deduction" => $deductLegalOt,
            "dates" => $dates
        ];
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
