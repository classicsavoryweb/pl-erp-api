<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\IncentiveHistory;
use App\Models\Incentives;
use App\Models\Position;
use Illuminate\Database\Seeder;

class IncentiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $jsonPath = public_path("jsons/payroll/incentives_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_incentives"] as $incentive) {

                $position = ltrim($incentive["position_id"], '0');

                $branch = Branch::where("branch_number", $incentive["tbranch"])->first();
                $employee = Employee::where("employee_number", $incentive["emp_id"])->first();

                $incentives = Incentives::create([
                    "employee_id" => $employee ? $employee->id : null,
                    "position_id" => $position ? $position : null,
                    "branch_id" => $branch ? $branch->id : null,
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

                $this->command->info("ID: {$incentives->id} out of 219951, File: incentives_{$i}.json out of 3");
            }
        }
    }
}
