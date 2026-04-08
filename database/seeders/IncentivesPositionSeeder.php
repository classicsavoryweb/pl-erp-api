<?php

namespace Database\Seeders;

use App\Models\IncentivePosition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncentivesPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        IncentivePosition::create([
            "position_id" => 10,
            "amount" => 2500,
            "oic_additional_amount" => 2000 
        ]);

        IncentivePosition::create([
            "position_id" => 5,
            "amount" => 2000,
            "oic_additional_amount" => 1000 
        ]);

        IncentivePosition::create([
            "position_id" => 7,
            "amount" => 4000,
            "oic_additional_amount" => 1000 
        ]);
    }
}
