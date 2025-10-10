<?php

namespace Database\Seeders;

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
        $jsonPath = public_path('jsons/biometrics/time_keeping.json');
        $jsonData = file_get_contents($jsonPath);
        $timekeeping_details = json_decode($jsonData, true);
        $unique_timekeeping_details = array_map("unserialize", array_unique(array_map("serialize", $timekeeping_details["time_keeping"])));

        foreach ($unique_timekeeping_details as $unique_timekeeping_detail) {
            TimeKeeping::create([
                "employee_id" => $unique_timekeeping_detail["employee_id"],
                "branch_id" => $unique_timekeeping_detail["branch_id"],
                "biometric_number" => $unique_timekeeping_detail["biometric_number"],
                "biotime_log_id" => $unique_timekeeping_detail["biotime_log_id"],
                "schedule_time_id" => $unique_timekeeping_detail["schedule_time_id"],
                "date" => $unique_timekeeping_detail["date"],
                "worked_hours" => $unique_timekeeping_detail["worked_hours"],
                "final_worked_hours" => $unique_timekeeping_detail["final_worked_hours"],
                "legal_holiday" => $unique_timekeeping_detail["legal_holiday"],
                "special_holiday" => $unique_timekeeping_detail["special_holiday"],
                "paid_holiday" => $unique_timekeeping_detail["paid_holiday"],
                "regular_overtime" => $unique_timekeeping_detail["regular_overtime"],
                "night_diff_hours" => $unique_timekeeping_detail["night_diff_hours"],
                "night_diff_overtime" => $unique_timekeeping_detail["night_diff_overtime"],
                "legal_holiday_hours" => $unique_timekeeping_detail["legal_holiday_hours"],
                "legal_holiday_overtime" => $unique_timekeeping_detail["legal_holiday_overtime"],
                "special_holiday_hours" => $unique_timekeeping_detail["special_holiday_hours"],
                "special_holiday_overtime" => $unique_timekeeping_detail["special_holiday_overtime"],
                "tardiness" => $unique_timekeeping_detail["tardiness"],
                "undertime" => $unique_timekeeping_detail["undertime"],
                "overtime_status" => $unique_timekeeping_detail["overtime_status"],
                "travel_time" => $unique_timekeeping_detail["travel_time"],
                "is_cancelled_off" => $unique_timekeeping_detail["is_cancelled_off"],
                "cancelled_off_hours" => $unique_timekeeping_detail["cancelled_off_hours"],
                "remarks" => $unique_timekeeping_detail["remarks"],
                "approved_overtime" => $unique_timekeeping_detail["approved_overtime"],
            ]);
        }
    }
}
