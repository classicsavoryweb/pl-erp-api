<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeRequirements;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeRequirementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/employee_requirements.json');
        $jsonData = file_get_contents($jsonPath);
        $requirements = json_decode($jsonData, true);

        foreach( $requirements["tbl_employeerequirements"] as $requirement) {
            $employee = Employee::where("employee_number", $requirement["employee_id"])->first();

            if ($employee) {
                EmployeeRequirements::create([
                    "employee_id" => $employee->id,
                    "employee_number" => $employee->employee_number,
                    "birth_certificate" => $requirement["birthcertificate"],
                    "marriage_certificate" => $requirement["marriagecertificate"],
                    "birth_certificate_dependents" => $requirement["birthcertificate_dependents"],
                    "sss" => $requirement["sss"],
                    "check_1902" => $requirement["check_1902"],
                    "check_1905" => $requirement["check_1905"],
                    "philhealth" => $requirement["philhealth"],
                    "pagibig" => $requirement["pagibig"],
                    "tin" => $requirement["tin"],
                    "picture2x2" => $requirement["picture2x2"],
                    "check_diploma" => $requirement["check_diploma"],
                    "tor" => $requirement["tor"],
                    "cot" => $requirement["cot"],
                    "coe" => $requirement["coe"],
                    "nbi" => $requirement["nbi"],
                    "drug_test" => $requirement["drugtest"],
                    "clinical_microscopy" => $requirement["clinicalmicroscopy"],
                    "chest_xray" => $requirement["chestxray"],
                    "occupational_permit" => $requirement["occupationalpermit"],
                ]);
            }
        }
    }
}
