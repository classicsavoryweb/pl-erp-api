<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Offense;
use App\Models\Sanction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeOffensesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/employee_offenses.json');
        $jsonData = file_get_contents($jsonPath);
        $offenses = json_decode($jsonData, true);

        foreach ($offenses["tbl_eoffenses"] as $empOffense) {
            $employee = Employee::where('employee_number', $empOffense['employee_id'])->first();
            $offense = Offense::where("offense_number", $empOffense['offense_id'])->first();
            
            $sanction = Sanction::where("description", $empOffense["sanction"])->first();
            if ($empOffense["sanction"] == "Written Memo") {
                $sanction = Sanction::where("description", "Written Reprimand")->first();
            }

            if ($offense && $employee) {
                $employee->offenses()->create([
                    "offense_id" => $offense->id,
                    "transgression" => $empOffense["transgession"],
                    'sanction_id' => $sanction->id ?? null,
                    'repetition' => $empOffense["repetition"],
                    'summary' => $empOffense["summary"],
                    'date_committed' => $empOffense["date_committed"] ,
                    'offense_action' => $empOffense["offense_action"],
                    'date_served_ao' => $empOffense["date_served_ao"],
                    'suspension_dates'=> $empOffense["suspension_dates"],
                    'date_served_da'=> $empOffense["date_served"],
                    'remarks'=> $empOffense["remarks"],
                ]);
            }
        }
    }
}
