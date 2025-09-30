<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IncentivesBracket;

class IncentivesBracketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/incentives_bracket.json');
        $jsonData = file_get_contents($jsonPath);
        $incentives_bracket_details = json_decode($jsonData, true);
        $unique_incentives_bracket_details = array_map("unserialize", array_unique(array_map("serialize", $incentives_bracket_details["tbl_positionrankfile"])));

        foreach ($unique_incentives_bracket_details as $unique_incentives_bracket_detail) {

            $bracket_id = str_replace(['BID000', 'BID00'], '', $unique_incentives_bracket_detail["bracket_id2"]);

            IncentivesBracket::create([
                "incentives_bracket_id" => $bracket_id,
                "bracket_name" => $unique_incentives_bracket_detail["bracketname"],
                "month" => $unique_incentives_bracket_detail["month"],
                "amount" => floatval(str_replace(',', '', $unique_incentives_bracket_detail["amount"])),
            ]);
        }
    }
}
