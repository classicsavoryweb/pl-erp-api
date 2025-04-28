<?php

namespace Database\Seeders;

use App\Models\EmployeeStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //active
        EmployeeStatus::create([
            "type" => "active",
            "description" => "Contractual"
        ]);
        EmployeeStatus::create([
            "type" => "active",
            "description" => "Probationary"
        ]);
        EmployeeStatus::create([
            "type" => "active",
            "description" => "Seasonal"
        ]);
        EmployeeStatus::create([
            "type" => "active",
            "description" => "Pending"
        ]);

        //inactive
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "AWOL"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Early End of Contract"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "End of Contract"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "No show"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Refused to renew"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Resigned"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Separated from the company"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Terminated"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "End of Probationary"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Pull-out"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Transferred to TTFC"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Retired"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Deceased"
        ]);
        EmployeeStatus::create([
            "type" => "inactive",
            "description" => "Data not available"
        ]);

    }
}
