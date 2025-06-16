<?php

namespace Database\Seeders;

use App\Models\CoeRequest;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CoeRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/coe_requests.json');
        $jsonData = file_get_contents($jsonPath);
        $requests = json_decode($jsonData, true);

        foreach ($requests["tbl_coe_request"] as $request) {

            $employee = Employee::where("employee_number", $request["employee_id2"])->first();
            
            if ($employee) {
                CoeRequest::create([
                    'employee_id' => $employee->id,
                    'date_requested' => $request["date_request"],
                    "include_salary" => $request["include_salary"],
                    "coe_format" => $request["coe_format"],
                    "coe_reason" => $request["reason"],
                    "status" => $request["status"],
                ]);
            }
        }
    }
}
