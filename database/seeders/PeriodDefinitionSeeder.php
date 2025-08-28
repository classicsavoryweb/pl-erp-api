<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PeriodDefinition;

class PeriodDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/period_definition.json');
        $jsonData = file_get_contents($jsonPath);
        $period_definition_details = json_decode($jsonData, true);
        $unique_period_definition_details = array_map("unserialize", array_unique(array_map("serialize", $period_definition_details["tbl_perioddefinition"])));

        foreach ($unique_period_definition_details as $unique_period_definition_detail) {

            $status = ucfirst($unique_period_definition_detail["period_status"]);
            if ($status === 'Opened') {
                $status = 'active';
            } else {
                $status = 'inactive';
            }

            PeriodDefinition::create([
                "month" => $unique_period_definition_detail["period_month"],
                "year" => $unique_period_definition_detail["period_year"],
                "status" => $status,
            ]);
        }
    }
}
