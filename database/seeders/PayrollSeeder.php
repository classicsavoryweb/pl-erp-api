<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Payroll;
use App\Models\TimeKeepingSummary;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $numeric = function ($value) {
            return is_numeric($value) ? $value + 0 : 0;
        };

        for ($i = 1; $i <= 13; $i++) {
            $jsonPath = public_path("jsons/payroll/payroll/payroll_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_payroll_process_hist"] as $unique_payroll) {

                $employee = Employee::where("employee_number", $unique_payroll["emp_id"])->first();

                if (!$employee) {
                    continue;
                }

                $cutoff = $unique_payroll["from_date"] . '-' . $unique_payroll["to_date"];

                $timekeepingSummary = TimeKeepingSummary::where("employee_id", $employee->id)
                ->where("cut_off", $cutoff)
                ->first();

                if (!$timekeepingSummary) {
                    continue;
                }

                $employeeDeductions = EmployeeDeduction::with(["deduction"])
                ->where("employee_id", $employee->id)
                ->where("balance", ">", 1)
                ->where("end_loan_status", "!=", "Yes")
                ->get();

                if (!$employeeDeductions) {
                    continue;
                }

                $deductions = [];
    
                foreach($employeeDeductions as $deduction) {
                    $amountToDeduct = $deduction->monthly_amortization / 2;
    
                    if ($amountToDeduct > $deduction->balance) {
                        $amountToDeduct = $deduction->balance;
                    }
    
                    $deductions[] = [
                        "deduction_id" => $deduction->id,
                        "type" => $deduction->deduction->description,
                        "monthly_amortization" => $deduction->monthly_amortization,
                        "balance" => $deduction->balance,
                        "deducted_amount" => $amountToDeduct,
                    ];
                }

                $contributionTotal = $numeric($unique_payroll["ee_sss"])
                                + $numeric($unique_payroll["ee_pagibig"])
                                + $numeric($unique_payroll["ee_philhealth"]);

                $additionalWorkingHoursTotal = $numeric($unique_payroll["total_ot2"])
                                + $numeric($unique_payroll["legal_hol2"])
                                + $numeric($unique_payroll["legal_holot2"])
                                + $numeric($unique_payroll["special_hol2"])
                                + $numeric($unique_payroll["special_holot2"])
                                + $numeric($unique_payroll["night_diff2"])
                                + $numeric($unique_payroll["ot_ndiff2"])
                                + $numeric($unique_payroll["txbl_adjustment"])
                                + $numeric($unique_payroll["cancelled_off"]);

                $workingHoursDeductionTotal = $numeric($unique_payroll["total_late2"])
                                + $numeric($unique_payroll["undertime2"]);


                $payroll = Payroll::create([
                    "employee_id" => $employee->id,
                    "timekeeping_summary_id" => $timekeepingSummary->id,
                    "timekeeping_branch_id" => $timekeepingSummary->branch_id,
                    "pay_date" => $unique_payroll["pay_date"],
                    "cut_off" => $timekeepingSummary->cut_off,
                    "start_date" => $unique_payroll["from_date"],
                    "end_date" => $unique_payroll["to_date"],
                    "daily_rate" => $unique_payroll["daily_rate"],
                    "hourly_rate" => $unique_payroll["hour_rate"],
                    "pay_type" => $unique_payroll["pay_type"],
                    "tax_status" => $unique_payroll["status"],
                    "salary" => $unique_payroll["salary"],
                    "tardiness_amount" => $unique_payroll["total_late2"],
                    "undertime_amount" => $unique_payroll["undertime2"],
                    "overtime_amount" => $unique_payroll["total_ot2"],
                    "legal_holiday_amount" => $unique_payroll["legal_hol2"],
                    "legal_holiday_overtime_amount" => $unique_payroll["legal_holot2"],
                    "special_holiday_amount" => $unique_payroll["special_hol2"],
                    "special_holiday_overtime_amount" => $unique_payroll["special_holot2"],
                    "night_differential_amount" => $unique_payroll["night_diff2"],
                    "night_differential_overtime_amount" => $unique_payroll["ot_ndiff2"],
                    "taxable_adjustment" => $unique_payroll["txbl_adjustment"],
                    "non_taxable_adjustment" => $unique_payroll["ntxbl_adjustment"],
                    "cancelled_off" => $unique_payroll["cancelled_off"],
                    "gross_pay" => $unique_payroll["gross_pay"],
                    "employee_sss" => $unique_payroll["ee_sss"],
                    "employer_sss" => $unique_payroll["er_sss"],
                    "employee_philhealth" => $unique_payroll["ee_philhealth"],
                    "employer_philhealth" => $unique_payroll["er_philhealth"],
                    "employee_pagibig" => $unique_payroll["ee_pagibig"],
                    "employer_pagibig" => $unique_payroll["er_pagibig"],
                    "withholding_tax" => $unique_payroll["withholding_tax"],
                    "deductions" => json_encode($deductions),
                    "total_deductions" => $unique_payroll["total_deductions"],
                    "manual_deductions" => $unique_payroll["manual_deduction"] ?? 0,
                    "net_pay" => $unique_payroll["net_pay"],
                    "status" => "final",
                    "remarks" => $unique_payroll["remarks"],
                    "taxable_income" => $unique_payroll["taxable_income"],
                    "contribution_total" => $contributionTotal,
                    "additional_working_hours_total" => $additionalWorkingHoursTotal,
                    "working_hours_deduction_total" => $workingHoursDeductionTotal,
                ]);

                $this->command->info("ID: {$payroll->id} out of 244536, File: payroll_{$i}.json out of 13");
            }
        }
    }
}
