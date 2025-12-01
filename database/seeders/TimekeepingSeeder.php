<?php

namespace Database\Seeders;

use App\Models\BiotimeLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingPayment;
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

                $paymentPerDay = $employee->daily_salary;
                $paymentPerHour =  $employee->schedule->schedule_type->name === "Compressed Schedule" ? $paymentPerDay / 9.5 : $paymentPerDay / 8;
                $paymentPerMinute = $paymentPerHour / 60;

                if (
                    ($employee->schedule->schedule_type->name === "Normal Schedule" && $timekeeping->final_worked_hours == 4) ||
                    ($employee->schedule->schedule_type->name === "Compressed Schedule" && $timekeeping->final_worked_hours == 4.75)
                ) {
                    $paymentPerDay = $employee->daily_salary / 2;
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

                $tardinessDeduction = $timekeeping->tardiness * $paymentPerMinute;
                $undertimeDeduction = $timekeeping->undertime * $paymentPerMinute;
                $legal_holiday = $timekeeping->legal_holiday_hours * $paymentPerHour;
                $special_holiday = $timekeeping->special_holiday_hours * ($paymentPerHour * .3);
                $night_differential = $timekeeping->night_diff_hours * ($paymentPerHour * .1);

                if ($employee->current_branch->type === "Branch") {
                    $timekeeping->regular_overtime = floor($timekeeping->regular_overtime);
                    $timekeeping->legal_holiday_overtime = floor($timekeeping->legal_holiday_overtime);
                    $timekeeping->special_holiday_overtime = floor($timekeeping->special_holiday_overtime);
                    $timekeeping->night_diff_overtime = floor($timekeeping->night_diff_overtime);
                }

                if ($timekeeping->regular_overtime > 0) {
                    if ($timekeeping->regular_overtime < $timekeeping->approved_overtime) {
                        $regular_overtime = $timekeeping->regular_overtime * ($paymentPerHour * 1.25);
                    } else {
                        $regular_overtime = $timekeeping->approved_overtime * ($paymentPerHour * 1.25);
                    }
                }
                
                if ($timekeeping->night_diff_overtime > 0) {
                    if ($timekeeping->night_diff_overtime < $timekeeping->approved_overtime) {
                        $night_differential_overtime = $timekeeping->night_diff_overtime * ($paymentPerHour * .125);
                    } else {
                        $night_differential_overtime = $timekeeping->approved_overtime * ($paymentPerHour * .125);
                    }
                }

                if ($timekeeping->legal_holiday_overtime > 0) {
                    if ($timekeeping->legal_holiday_overtime <= $timekeeping->approved_overtime) {
                        $legal_holiday_overtime = $timekeeping->legal_holiday_overtime * ($paymentPerHour * 2.6);
                    } else {
                        $legal_holiday_overtime = $timekeeping->approved_overtime * ($paymentPerHour * 2.6);
                    }
                }

                if ($timekeeping->special_holiday_overtime > 0) {
                    if ($timekeeping->special_holiday_overtime <= $timekeeping->approved_overtime) {
                        $special_holiday_overtime = $timekeeping->special_holiday_overtime * ($paymentPerHour * 1.69);
                    } else {
                        $special_holiday_overtime = $timekeeping->approved_overtime * ($paymentPerHour * 1.69);
                    }
                }

                $totalAmount = ($paymentPerDay + $regular_overtime + $night_differential + $night_differential_overtime + $legal_holiday + $special_holiday + $legal_holiday_overtime + $special_holiday_overtime + $cancelled_off)
                    - ($tardinessDeduction + $undertimeDeduction);

                if ($timekeeping->is_cancelled_off) {
                    $cancelledOffWorkedHours = $timekeeping->cancelled_off_hours;

                    if ($employee->schedule->schedule_type->name === "Normal Schedule") {
                        $cancelledOffWorkedHours = $timekeeping->cancelled_off_hours < 8 ? $timekeeping->cancelled_off_hours : 8;
                    } else {
                        $cancelledOffWorkedHours = $timekeeping->cancelled_off_hours < 9.5 ? $timekeeping->cancelled_off_hours : 9.5;
                    }

                    $cancelled_off = $cancelledOffWorkedHours * ($paymentPerHour * 1.3);

                    if ($timekeeping->legal_holiday === "Yes") {
                        $cancelled_off = $cancelledOffWorkedHours * ($paymentPerHour * 1.6);
                    }

                    $totalAmount = $cancelled_off;
                }

                TimeKeepingPayment::create([
                    "timekeeping_id" => $timekeeping->id,
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

                $this->command->info("ID: {$timekeeping->id} out of 2994774, File: timekeeping_{$i}.json out of 32");
            }
        }
    }
}
