<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeVaccine;
use App\Models\Vaccine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VaccinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/vaccination.json');
        $jsonData = file_get_contents($jsonPath);
        $vaccination = json_decode($jsonData, true);

        foreach( $vaccination["tbl_vaccination"] as $vax ) {
            $vaxId = substr($vax["brand_id2"], -1);
            $employee = Employee::where("employee_number" , $vax["employee_id2"])->first();
            $vaccine = Vaccine::find($vaxId);

            if ($employee && $vaccine) {
                EmployeeVaccine::create([
                    "employee_id" => $employee->id,
                    "vaccine_id" => $vaccine->id,
                    "dose" => $vax["dose"],
                    "status" => $vax["status"],
                    "date" => $vax["date"]
                ]);
            }
        }
    }
}
