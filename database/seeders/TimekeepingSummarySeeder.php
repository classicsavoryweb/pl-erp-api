<?php

namespace Database\Seeders;

use App\Models\TimeKeepingSummary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TimekeepingSummarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/biometrics/timekeeping_summary.json');
        $jsonData = file_get_contents($jsonPath);
        $timekeeping_details = json_decode($jsonData, true);
        $unique_timekeeping_details = array_map("unserialize", array_unique(array_map("serialize", $timekeeping_details["timekeeping_summary"])));

        foreach ($unique_timekeeping_details as $unique_timekeeping_detail) {
            TimeKeepingSummary::create([
                "cut_off" => $unique_timekeeping_detail["cut_off"],
                "employee_id" => $unique_timekeeping_detail["employee_id"],
                "branch_id" => $unique_timekeeping_detail["branch_id"],
                "regular_days" => $unique_timekeeping_detail["regular_days"],
                "special_leave" => $unique_timekeeping_detail["special_leave"],
                "cancelled_off" => $unique_timekeeping_detail["cancelled_off"],
                "special_holiday" => $unique_timekeeping_detail["special_holiday"],
                "legal_holiday" => $unique_timekeeping_detail["legal_holiday"],
                "regular_overtime" => $unique_timekeeping_detail["regular_overtime"],
                "night_differential" => $unique_timekeeping_detail["night_differential"],
                "night_differential_overtime" => $unique_timekeeping_detail["night_differential_overtime"],
                "legal_holiday_overtime" => $unique_timekeeping_detail["legal_holiday_overtime"],
                "special_holiday_overtime" => $unique_timekeeping_detail["special_holiday_overtime"],
                "undertime" => $unique_timekeeping_detail["undertime"],
                "tardiness" => $unique_timekeeping_detail["tardiness"],
                "travel_time" => $unique_timekeeping_detail["travel_time"],
                "adjustment_amount" => $unique_timekeeping_detail["adjustment_amount"],
                "remarks" => $unique_timekeeping_detail["remarks"],
            ]);
        }
    }
}
