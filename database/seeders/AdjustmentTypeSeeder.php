<?php

namespace Database\Seeders;

use App\Models\AdjustmentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdjustmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        AdjustmentType::create([
            "description" => "Incorrect logs",
            "status" => "active"
        ]);

        AdjustmentType::create([
            "description" => "Incomplete logs",
            "status" => "active"
        ]);

        AdjustmentType::create([
            "description" => "No logs with schedule",
            "status" => "active"
        ]);

        AdjustmentType::create([
            "description" => "Incorrect schedule",
            "status" => "active"
        ]);

        AdjustmentType::create([
            "description" => "Blank schedule with logs",
            "status" => "active"
        ]);

    }
}
