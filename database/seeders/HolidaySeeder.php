<?php

namespace Database\Seeders;

use App\Models\BranchHoliday;
use Illuminate\Database\Seeder;
use App\Models\Holiday;
use App\Models\Branch;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $legalHolidayPath = public_path('jsons/biometrics/legal_holidays.json');
        $specialHolidayPath = public_path('jsons/biometrics/special_holidays.json');

        $legalHolidayData = file_get_contents($legalHolidayPath);
        $specialHolidayData = file_get_contents($specialHolidayPath);

        $legalHolidayDetails = json_decode($legalHolidayData, true);
        $specialHolidayDetails = json_decode($specialHolidayData, true);

        $mergedHolidayDetails = array_merge($legalHolidayDetails["tbl_bio_holiday_lh"], $specialHolidayDetails["tbl_bio_holiday_sh"]);

        $uniqueHolidayDetails = array_map("unserialize", array_unique(array_map("serialize", $mergedHolidayDetails)));

        foreach ($uniqueHolidayDetails as $holidayDetail) {
            $branch = '';
            $holidayType = '';
            $short_name = '';
            $description = '';
            $date = $holidayDetail["date"];
            if (isset($holidayDetail['holiday1'])) {
                $holidayType = $holidayDetail['holiday1'];
                $branch = Branch::where("short_name", $holidayDetail["branch1"])->first();
                $description = $holidayDetail['description'];
            } elseif (isset($holidayDetail['holiday2'])) {
                $holidayType = $holidayDetail['holiday2'];
                $branch = Branch::where("short_name", $holidayDetail["branch2"])->first();
                $description = $holidayDetail['description2'];
            }

            if ($branch) {
                $holiday = Holiday::where("date", $date)->first();
                if (!$holiday) {
                    $holiday = Holiday::create([
                        "description" => $description,
                        "date" => $date,
                        "holiday_type" => $holidayType
                    ]);
                }
    
                BranchHoliday::create([
                    "branch_id" => $branch->id,
                    "holiday_id" => $holiday->id,
                ]);
            }
        }
    }
}
