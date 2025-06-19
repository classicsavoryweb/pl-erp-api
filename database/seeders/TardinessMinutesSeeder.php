<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\TardinessMinutes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TardinessMinutesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/tardiness_minutes.json');
        $jsonData = file_get_contents($jsonPath);
        $tardinessMinutes = json_decode($jsonData, true);

        foreach ($tardinessMinutes["tbl_tardiness_minutes"] as $minutes) {
            $employee = Employee::where("employee_number", $minutes['emp_id'])->first();

            if ($employee) {
                TardinessMinutes::create([
                    "employee_id" => $employee->id,
                    "late_date" => $minutes["late_date"],
                    "late_minutes" => $minutes["late_minutes"],
                    "month" => $minutes["month"],
                    "year" => $minutes["year"],
                    "reason" => $minutes["reason"]
                ]);
            }
        }
    }
}
