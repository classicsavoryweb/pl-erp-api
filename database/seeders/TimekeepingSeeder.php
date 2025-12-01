<?php

namespace Database\Seeders;

use App\Models\BiotimeLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TimekeepingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 32; $i++) {
            $jsonPath = public_path("jsons/biometrics/timekeeping/timekeeping_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_bio_detailedpayroll"] as $unique_timekeeping) {

                $employee = Employee::where("biometric_number", $unique_timekeeping["employee_id2"])->first();

                if (!$employee) {
                    continue;
                }

                $branch = Branch::where("short_name", $unique_timekeeping["branch"])->first();

                $biotime_log = BiotimeLog::where("employee_id", $employee->id)
                ->where("date", $unique_timekeeping["date"])->first();

                if (!$biotime_log) {
                    continue;
                }

                $schedule_time = ScheduleTime::where("employee_id", $employee->id)
                ->where("date", $unique_timekeeping["date"])->first();

                if (!$schedule_time) {
                    continue;
                }

                $holiday = Holiday::where("date", $unique_timekeeping["date"])->first();

                $holiday_type = $holiday->holiday_type ?? null;

                $timekeeping = TimeKeeping::create([
                    "employee_id" => $employee->id,
                    "branch_id" => $branch->id ?? $employee->branch_id,
                    "biometric_number" => $employee->biometric_number,
                    "biotime_log_id" => $biotime_log->id ?? null,
                    "schedule_time_id" => $schedule_time->id ?? null,
                    "date" => $unique_timekeeping["date"],
                    "worked_hours" => 8,
                    "final_worked_hours" => 8,
                    "legal_holiday" => ($holiday_type === 'LH') ? 'Yes' : 'No',
                    "special_holiday" => ($holiday_type === 'SH') ? 'Yes' : 'No',
                    "paid_holiday" => null,
                    "regular_overtime" => $unique_timekeeping["ot_reg"],
                    "night_diff_hours" => $unique_timekeeping["night_diff"],
                    "night_diff_overtime" => $unique_timekeeping["night_diffot"],
                    "legal_holiday_hours" => $unique_timekeeping["legal_holiday"],
                    "legal_holiday_overtime" => $unique_timekeeping["legal_holot"],
                    "special_holiday_hours" => $unique_timekeeping["special_holiday"],
                    "special_holiday_overtime" => $unique_timekeeping["special_holot"],
                    "tardiness" => $unique_timekeeping["late"],
                    "undertime" => $unique_timekeeping["undertime"],
                    "travel_time" => 0,
                    "cancelled_off_hours" => $unique_timekeeping["cancelled_off"],
                    "is_cancelled_off" => $unique_timekeeping["cancelled_off"] ? 1 : 0,
                ]);

                $this->command->info("ID: {$timekeeping->id} out of 2994774, File: timekeeping_{$i}.json out of 32");
            }
        }
    }
}
