<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\IncentiveHistory;
use App\Models\Incentives;
use Illuminate\Database\Seeder;

class IncentiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $jsonPath = public_path("jsons/payroll/incentive_history_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_incentives"] as $incentive) {
                $employee = Employee::where("employee_number", $incentive["emp_id"])->first();

                $incentiveHistory = Incentives::create([
                    "employee_id" => $employee ? $employee->id : null,
                    "incentive_amount" => $incentive["amount"],
                    "max_amount" => $incentive["maxamount"],
                    "remarks" => $incentive["remarks"],
                    "pay_date" => $incentive["pay_date"],
                    "amount_rate" => $incentive["amount_rate"],
                    "additional_incentive" => $incentive["incentive_add"],
                    "adjustment_days" => $incentive["adjustment_days"],
                    "adjustment_percent" => $incentive["adjustment_percent"],
                    "status" => "final"
                ]);

                $this->command->info("ID: {$incentiveHistory->id} out of 219951, File: incentive_history_{$i}.json out of 3");
            }
        }
    }
}
