<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\IncentivesManagement;
use App\Models\Employee;

class IncentivesManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/incentives_management.json');
        $jsonData = file_get_contents($jsonPath);
        $incentives_management_details = json_decode($jsonData, true);
        $unique_incentives_management_details = array_map("unserialize", array_unique(array_map("serialize", $incentives_management_details["tbl_positionmanagement"])));

        foreach ($unique_incentives_management_details as $unique_incentives_management_detail) {
            $employee = Employee::where("employee_number", $unique_incentives_management_detail["employee_id2"])->first();

            IncentivesManagement::create([
                "employee_id" => $employee->id,
                "amount" => $unique_incentives_management_detail["amount"],
                "max_amount" => $unique_incentives_management_detail["maxamount"]
            ]);
        }
    }
}
