<?php

namespace Database\Seeders;

use App\Models\Cutoff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CutOffInitializeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/cut_off.json');
        $jsonData = file_get_contents($jsonPath);
        $cutOffs = json_decode($jsonData, true);

        foreach ($cutOffs["tbl_initialize_cutoff"] as $cutOff) {
            // "initialize_id": "1",
            // "pay_date": "2017-12-29",
            // "1st_part": "0",
            // "2nd_part": "1",
            // "normal_payroll": "1",
            // "from_date": "2017-12-11",
            // "to_date": "2017-12-25",
            // "regular_days": "0.0",
            // "legal_hol": "0",
            // "special_hol": "0.00",
            // "notes": ""
            $cutOffDate = $cutOff["from_date"]."-".$cutOff["to_date"];

            $isExisting = Cutoff::where("cut_off", $cutOffDate)->first();

            if (!$isExisting) {
                Cutoff::create(attributes: [
                    "cut_off" => $cutOffDate,
                    "start_date" => $cutOff["from_date"],
                    "end_date" => $cutOff["to_date"],
                    "is_cutoff_initialized" => true,
                    "pay_date" => $cutOff["pay_date"],
                    "status" => "inactive",
                    "initializations" => null,
                    "is_timekeeping_locked" => true,
                    "is_cutoff_locked" => true,
                    "part" => $cutOff["1st_part"] === "1" ? 1 : 2
                ]);
            }
        }
    }
}
