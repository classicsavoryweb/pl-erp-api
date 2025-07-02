<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ScheduleMasterlist;

class ScheduleMasterlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/biometrics/schedule_masterlist.json');
        $jsonData = file_get_contents($jsonPath);
        $schedule_details = json_decode($jsonData, true);
        $unique_schedule_details = array_map("unserialize", array_unique(array_map("serialize", $schedule_details["tbl_bio_masterfile"])));

        foreach ($unique_schedule_details as $schedule_detail) {
            ScheduleMasterlist::create([
                "start_time" => $schedule_detail["start_time"],
                "end_time" => $schedule_detail["end_time"],
                "selection" => $schedule_detail["selection"],
                "classification" => $schedule_detail["classification"],
                "time_break" => $schedule_detail["time_break"],
                "reg_hours" => $schedule_detail["reg_hours"],
                "days" => $schedule_detail["days"],
                "required" => $schedule_detail["required"],
            ]);
        }
    }
}
