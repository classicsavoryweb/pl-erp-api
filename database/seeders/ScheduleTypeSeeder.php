<?php

namespace Database\Seeders;

use App\Models\ScheduleType;
use Illuminate\Database\Seeder;

class ScheduleTypeSeeder extends Seeder
{
    public function run(): void
    {
        ScheduleType::create([
            "name" => "Compressed Schedule",
            "status" => "active"
        ]);

        ScheduleType::create([
            "name" => "Normal Schedule",
            "status" => "active"
        ]);

        ScheduleType::create([
            "name" => "Others",
            "status" => "active"
        ]);
    }
}
