<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Tardiness;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TardinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/tardiness_history.json');
        $jsonData = file_get_contents($jsonPath);
        $tardiness = json_decode($jsonData, true);

        foreach($tardiness["tbl_tardiness_hist"] as $tardy) {
            $employee = Employee::where("employee_number", $tardy["employee_id2"])->first();
            $branch = $employee->current_branch()->first();

            $status = $tardy["ao_status"];
            if ($tardy["voided"] !== "No") {
                $status = "Voided";
            }

            Tardiness::create([
                "employee_id" => $employee->id,
                "branch_id" => $branch->id,
                "month" => $tardy["month"],
                "year" => $tardy["year"],
                "total_minutes" => $tardy["total_minutes"],
                "offense_punishment" => $tardy["offense_punishment"],
                "tardiness_sanction_id" => $tardy["offense_id2"],
                "reason" => $tardy["reason"] ,
                "status" => $status ?? "N/A",
                "da_status" =>  null,
                "mail_status" => $tardy["mail_status"],
            ]);
        }

        $jsonPath1 = public_path('jsons/tardiness_active.json');
        $jsonData1 = file_get_contents($jsonPath1);
        $tardinessActive = json_decode($jsonData1, true);

        foreach($tardinessActive["tbl_tardiness"] as $tardy) {
            $employee = Employee::where("employee_number", $tardy["employee_id2"])->first();
            $branch = $employee->current_branch()->first();

            $status = $tardy["ao_status"];
            if ($tardy["voided"] !== "No") {
                $status = "Voided";
            }

            Tardiness::create([
                "employee_id" => $employee->id,
                "branch_id" => $branch->id,
                "month" => $tardy["month"],
                "year" => $tardy["year"],
                "total_minutes" => $tardy["total_minutes"],
                "offense_punishment" => $tardy["offense_punishment"],
                "tardiness_sanction_id" => $tardy["offense_id2"],
                "reason" => $tardy["reason"] ,
                "status" => $status ?? "N/A",
                "da_status" =>  null,
                "mail_status" => $tardy["mail_status"],
            ]);
        }
    }
}
