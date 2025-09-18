<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\DeductionHistory;
use Illuminate\Database\Seeder;

class DeductionHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $jsonPath = public_path("jsons/payroll/deduction_history_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_trans_deduction_hist2"] as $deduction) {
                $employee = Employee::where("employee_number", $deduction["emp_id"])->first();

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

                $deductionHistory = DeductionHistory::create([
                    "employee_id" => $employee ? $employee->id : null,
                    "reference_doc" => $deduction["reference_doc"],
                    "deduction_id" => $deduction["deduction_id2"],
                    "amount" => $deduction["amount"],
                    "pay_date" => $deduction["pay_date"],
                    "date_time" => $deduction["datetime"],
                ]);

                $this->command->info("ID: {$deductionHistory->id} out of 454762, File: deduction_history_{$i}.json out of 3");
            }
        }
    }
}
