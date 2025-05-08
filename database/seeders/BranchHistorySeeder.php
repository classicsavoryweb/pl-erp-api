<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class BranchHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/branch_employee.json');
        $jsonData = file_get_contents($jsonPath);
        $branches = json_decode($jsonData, true);

        foreach($branches["tbl_ebranch"] as $branch) {
            $employee = Employee::where("employee_number", $branch["employee_id"])->first();
            $updatedBranch = Branch::where("branch_number", $branch["branch_id"])->first();
            if ($updatedBranch && $employee) {

                BranchEmployee::create([
                    "employee_id" => $employee->id,
                    "branch_id" => $updatedBranch->id,
                    "date_assigned" => $branch["date_assigned"],
                    "remarks" => $branch["remarks"]
                ]);
            }
        }
    }
}
