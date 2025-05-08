<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Position;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;




class PositionHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/employee_position.json');
        $jsonData = file_get_contents($jsonPath);
        $positions = json_decode($jsonData, true);

        foreach($positions["tbl_eposition"] as $position) {
            $employee = Employee::where("employee_number", $position["employee_id"])->first();
            $updatedPosition = Position::where("description", $position["position_descr"])->first();

            if ($updatedPosition && $employee) {
                EmployeePosition::create([
                    "employee_id" => $employee->id,
                    "position_id" => $updatedPosition->id,
                    "date_started" => $position["date_started"],
                    "remarks" => $position["remarks"]
                ]);
            }
        }
    }
}
