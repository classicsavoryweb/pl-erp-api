<?php

namespace Database\Seeders;

use App\Models\ActingCapacityReason;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActingCapacityReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ActingCapacityReason::create([
            "reason" => "Extended",
            "status" => "active"
        ]);
        ActingCapacityReason::create([
            "reason" => "Fast Track",
            "status" => "active"
        ]);
    }
}
