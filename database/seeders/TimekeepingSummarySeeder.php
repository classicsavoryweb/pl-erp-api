<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\TimeKeepingSummary;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TimekeepingSummarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $jsonPath = public_path("jsons/biometrics/timekeeping_summary/timekeeping_summary_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_bio_payrollprocess_hist"] as $unique_timekeeping) {

                $employee = Employee::where("biometric_number", $unique_timekeeping["employee_id2"])
                ->first();

                if (!$employee) {
                    continue;
                }

                $branch = Branch::where("short_name", $unique_timekeeping["branch"])->first();

                if (!$branch) {
                    continue;
                }

                $tardiness = 0;

                for ($k = 1; $k <= 9; $k++) {
                    $tardiness += $unique_timekeeping["late{$k}"];
                }

                $cutoff = $this->getCutOffForDate($unique_timekeeping["process_date"]);

                $timekeeping_summary = TimeKeepingSummary::create([
                    "cut_off" => $cutoff['start_time'] . '-' . $cutoff['end_time'],
                    "employee_id" => $employee->id,
                    "branch_id" => $branch->id ?? $employee->branch_id,
                    "regular_days" => $unique_timekeeping["reg_days"],
                    "special_leave" => $unique_timekeeping["special_leave"],
                    "cancelled_off" => $unique_timekeeping["cancelled_off"],
                    "special_holiday" => $unique_timekeeping["special_holiday"],
                    "legal_holiday" => $unique_timekeeping["legal_holiday"],
                    "regular_overtime" => $unique_timekeeping["ot_reg"],
                    "night_differential" => $unique_timekeeping["night_diff"],
                    "night_differential_overtime" => $unique_timekeeping["night_diffot"],
                    "legal_holiday_overtime" => $unique_timekeeping["legal_holot"],
                    "special_holiday_overtime" => $unique_timekeeping["special_holot"],
                    "tardiness" => $tardiness,
                    "undertime" => $unique_timekeeping["undertime"],
                    "travel_time" => 0,
                    "adjustment_amount" => $unique_timekeeping["adjustment_amt"],
                ]);

                // $this->command->info("ID: {$timekeeping_summary->id} out of 173941, File: timekeeping_summary_{$i}.json out of 4");
            }
        }
    }

    public function getCutOffForDate($date){
        $carbonDate = Carbon::parse($date);
        $day = $carbonDate->day;

        if ($day >= 1 && $day <= 15) {
            $startTime = $carbonDate->copy()->subMonth()->startOfMonth()->addDays(25)->startOfDay(); //26
            $endTime = $carbonDate->copy()->startOfMonth()->addDays(9)->endOfDay(); //10
        } else {
            $startTime = $carbonDate->copy()->startOfMonth()->startOfMonth()->addDays(10)->startOfDay(); //11
            $endTime = $carbonDate->copy()->startOfMonth()->addDays(24)->endOfDay(); //25
        }

        return [
            'start_time' => $startTime->toDateString(),
            'end_time' => $endTime->toDateString(),
        ];
    }
}
