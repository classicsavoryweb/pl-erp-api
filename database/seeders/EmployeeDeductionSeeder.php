<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use Illuminate\Database\Seeder;

class EmployeeDeductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $jsonPath = public_path("jsons/payroll/employee_deductions/transaction_deduction_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_trans_deduction"] as $deduction) {
                $employee = Employee::where("employee_number", $deduction["emp_id"])->first();
                $branch = Branch::where("branch_number", $deduction["branch"])->first();

                $deductionIdMap = [
                    '00000011' => 10,
                    '00000012' => 11,
                    '00000013' => 12,
                    '00000014' => 13,
                    '00000015' => 14,
                    '00000016' => 15,
                    '00000017' => 16,
                    '00000018' => 17,
                    '00000019' => 18,
                ];

                if (array_key_exists($deduction["deduction_id2"], $deductionIdMap)) {
                    $deduction["deduction_id2"] = $deductionIdMap[$deduction["deduction_id2"]];
                } else {
                    $deduction["deduction_id2"] = ltrim($deduction["deduction_id2"], '0');
                }

                $employeeDeduction = EmployeeDeduction::create([
                    "employee_id" => $employee ? $employee->id : null,
                    "deduction_id" => $deduction["deduction_id2"],
                    "reference_doc" => $deduction["reference_doc"],
                    "months" => $deduction["months"],
                    "amount" => floatval(str_replace(',', '', $deduction["amount"])),
                    "source_of_fund" => $deduction["source_of_fund"],
                    "start_date" => $deduction["start_date"],
                    "end_date" => $deduction["end_date"],
                    "monthly_amortization" => floatval(str_replace(',', '', $deduction["monthly_amortization"])),
                    "balance" => floatval(str_replace(',', '', $deduction["balance"])),
                    "remarks" => $deduction["remarks"],
                    "debit_id" => $deduction["debit_account_id"],
                    "credit_id" => $deduction["credit_account_id"],
                    "branch_id" => $branch ? $branch->id : null,
                    "block_payment" => $deduction["block_payment"],
                    "approved_by" => $deduction["approved_by"],
                    "approved_date" => $deduction["approved_date"],
                    "approved_status" => $deduction["approved_status"],
                    "deduction_ref" => $deduction["deduction_ref"],
                    "deduction_status" => $deduction["deduction_status"],
                    "created_by" => $deduction["created_by"],
                    "date_created" => $deduction["date_created"],
                    "end_loan_reason" => $deduction["endloan_reason"],
                    "end_loan_status" => $deduction["endloan_status"],
                ]);

                $this->command->info("ID: {$employeeDeduction->id} out of 233373, File: transaction_deduction_{$i}.json out of 6");
            }
        }
    }
}
