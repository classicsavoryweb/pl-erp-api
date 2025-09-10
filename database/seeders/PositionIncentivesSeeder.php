<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PositionIncentives;

class PositionIncentivesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/position_incentives.json');
        $jsonData = file_get_contents($jsonPath);
        $position_incentives_details = json_decode($jsonData, true);
        $unique_position_incentives_details = array_map("unserialize", array_unique(array_map("serialize", $position_incentives_details["tbl_positionrankfile2"])));

        foreach ($unique_position_incentives_details as $unique_position_incentives_detail) {

            PositionIncentives::create([
                "position_id" => $unique_position_incentives_detail["position_id2"],
                "incentives_bracket_id" => $unique_position_incentives_detail["bracket_id2"],
            ]);
        }
    }
}
