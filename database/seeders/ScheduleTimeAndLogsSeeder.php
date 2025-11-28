<?php

namespace Database\Seeders;

use App\Models\BiotimeLog;
use App\Models\BiotimePunch;
use App\Models\Employee;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use Illuminate\Database\Seeder;
use Log;

class ScheduleTimeAndLogsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function isValidTime($value)
    {
        return preg_match("/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/", $value);
    }

    public function convertScheduleBlock($block)
    {
        $lines = array_filter(array_map('trim', explode("\n", $block)));
        $converted = [];

        $hoSchedules = [
            '9:30AM TO 18:30PM',
            '8:30AM TO 17:30PM',
            '8:00AM TO 6:30PM',
            '9:00AM TO 7:30PM',
            '7:00AM TO 5:30PM',
        ];

        foreach ($lines as $line) {
            if (!preg_match('/(\d{1,2}:\d{2}\s?[AP]M)\s*-\s*(\d{1,2}:\d{2}\s?[AP]M)/i', $line, $m)) {
                continue;
            }

            $start12 = strtoupper(trim($m[1]));
            $end12   = strtoupper(trim($m[2]));

            $startTimestamp = strtotime($start12);
            $endTimestamp   = strtotime($end12);

            if ($endTimestamp <= $startTimestamp) {
                $endTimestamp = strtotime('+1 day', $endTimestamp);
            }

            $startHourInt = (int)date("H", $startTimestamp);
            $startMinutes = date("i", $startTimestamp);

            $startHour = ($startHourInt >= 5 && $startHourInt <= 9) ? ltrim($startHourInt, '0') : str_pad($startHourInt, 2, '0', STR_PAD_LEFT);
            $startFinal = $startHour . ':' . $startMinutes . ($startHourInt >= 12 ? 'PM' : 'AM');

            $endHourInt = (int)date("H", $endTimestamp);
            $endMinutes = date("i", $endTimestamp);
            $endHourFormatted = ($endHourInt === 0) ? '12' : str_pad($endHourInt, 2, '0', STR_PAD_LEFT);
            $endFinal = $endHourFormatted . ':' . $endMinutes . ($endHourInt >= 12 ? 'PM' : 'AM');

            $scheduleString = strtoupper($startFinal . " TO " . $endFinal);

            if (in_array($scheduleString, array_map('strtoupper', $hoSchedules))) {
                $scheduleString = 'HO ' . $scheduleString;
            }

            $converted[] = $scheduleString;
        }

        return implode("\n", $converted);
    }

    public function run(): void
    {
        for ($i = 1; $i <= 37; $i++) {
            $jsonPath = public_path("jsons/biometrics/schedule_time/schedule_time_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_bio_schedule"] as $schedule) {
                $employee = Employee::where("biometric_number", $schedule["employee_id2"])->first();

                if (!$employee) {
                    continue;
                }

                $scheduleFromList = ScheduleMasterlist::where('selection', trim($schedule["actual_schedule"]))->first();

                if ($scheduleFromList) {
                    $scheduleValue = $scheduleFromList->selection;
                } else if ($schedule["actual_schedule"] === "IL") {
                    $scheduleValue = "INCENTIVE LEAVE";
                } else if ($schedule["actual_schedule"] === " INCENTIVE LEAVE") {
                    $scheduleValue = "INCENTIVE LEAVE";
                } else if ($schedule["actual_schedule"] === "ABSENT" || 
                           $schedule["actual_schedule"] === "D.A." ||
                           $schedule["actual_schedule"] === "AWOL" ||
                           $schedule["actual_schedule"] === "RESIGNED") {
                    $scheduleValue = "NO SCHEDULE";
                } else if ($schedule["actual_schedule"] === "12:00 PM - 2:00 AM" ||
                           $schedule["actual_schedule"] === "2:00 PM - 1:00 AM"  ||
                           $schedule["actual_schedule"] === "12:00 PM - 11:00 PM"  ||
                           $schedule["actual_schedule"] === "11:00 AM - 7:00 PM"  ||
                           $schedule["actual_schedule"] === "9:00 AM - 1:00 PM"  ||
                           $schedule["actual_schedule"] === "11:30 AM - 8:30 PM"  ||
                           $schedule["actual_schedule"] === "7:30 AM - 4:30 PM"  ||
                           $schedule["actual_schedule"] === "9:00 AM - 8:00 PM") {
                    continue;
                } else if ($schedule["actual_schedule"] === "HOLIDAY" ||
                           strlen($schedule["actual_schedule"]) === 3 ||
                           $schedule["actual_schedule"] === "SCT2" ||
                           $schedule["actual_schedule"] === "BROMT/SEMINAR") {
                    $scheduleValue = "Flex";
                } else {
                    if (str_contains($schedule["actual_schedule"], "-")) {
                        $scheduleValue = $this->convertScheduleBlock($schedule["actual_schedule"]);
                    } else {
                        $scheduleValue = $schedule["actual_schedule"];
                    }
                }

                $scheduleBasedOnActualSched = ScheduleMasterlist::whereRaw(
                    "LOWER(REPLACE(selection,' ','')) = ?",
                    [ strtolower(str_replace(' ', '', $scheduleValue)) ]
                )->first();

                try {

                    $data = [
                        "employee_id"      => $employee->id,
                        "branch_id"        => $employee->current_branch->id,
                        "biometric_number" => $employee->biometric_number,
                        "date"             => $schedule["date"],
                        "timetable"        => $scheduleValue,
                        "check_in"         => $scheduleBasedOnActualSched->start_time,
                        "check_out"        => $scheduleBasedOnActualSched->end_time,
                    ];

                    $scheduleTime = ScheduleTime::create($data);

                } catch (\Exception $e) {

                    \Log::error('Failed to create ScheduleTime record:', [
                        'error'   => $e->getMessage(),
                        'data'    => isset($data) ? $data : null,
                        'employee_id' => $employee->id,
                        'branch_id' => $employee->current_branch->id,
                        'scheduleBasedOnActualSched' => $scheduleBasedOnActualSched,
                        'trace'   => $e->getTraceAsString(),
                    ]);

                    throw $e;
                }

                $skipSchedules = [
                    "LEAVE",
                    "INCENTIVE LEAVE",
                    "NO SCHEDULE",
                    "SUSPENSION",
                    "REST DAY",
                ];

                $punchFields = ["act_timein", "act_breakout", "act_breakin", "act_timeout"];
                $validPunches = [];

                foreach ($punchFields as $field) {
                    $time = $schedule[$field];
                    if ($this->isValidTime($time)) {
                        $validPunches[] = $time;
                    }
                }

                if (count($validPunches) === 0) {
                    continue;
                }

                if (in_array(strtoupper($scheduleValue), $skipSchedules)) {
                    continue;
                }

                $bioLog = BiotimeLog::create([
                    "employee_id"      => $employee->id,
                    "biometric_number" => $employee->biometric_number,
                    "date"             => $schedule["date"],
                    "remarks"          => null,
                    "is_transferred"   => 0
                ]);

                foreach ($punchFields as $field) {
                    $time = $schedule[$field];

                    if (!$this->isValidTime($time)) {
                        continue;
                    }

                    BiotimePunch::create([
                        "biotime_log_id" => $bioLog->id,
                        "date"           => $schedule["date"],
                        "punch_time"     => $schedule["date"] . " " . $time,
                        "branch_id"      => null,
                        "remarks"        => null,
                    ]);
                }

                $this->command->info("ID: {$scheduleTime->id} out of 3,310,228, File: schedule_time_{$i}.json out of 37");
            }
        }
    }
}
