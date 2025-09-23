<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\IncentivesPerEmployee;
use App\Models\Employee;

class IncentivesPerEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/incentives_per_employee.json');
        $jsonData = file_get_contents($jsonPath);
        $incentives_per_employee_details = json_decode($jsonData, true);
        $unique_incentives_per_employee_details = array_map("unserialize", array_unique(array_map("serialize", $incentives_per_employee_details["tbl_rankfile_peremp"])));

        foreach ($unique_incentives_per_employee_details as $unique_incentives_per_employee_detail) {
            $employee = Employee::where("employee_number", $unique_incentives_per_employee_detail["employee_id2"])->first();

            IncentivesPerEmployee::create([
                "employee_id" => $employee->id,
                "amount" => floatval(str_replace(',', '', $unique_incentives_per_employee_detail["amount"])),
                "max_amount" => floatval(str_replace(',', '', $unique_incentives_per_employee_detail["maxamount"]))
            ]);
        }
    }
}
