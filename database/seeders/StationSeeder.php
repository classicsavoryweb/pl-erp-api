<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Station::create([
            "name" => "Front of the house",
            "station_order" => 1,
        ]);

        Station::create([
            "name" => "Back of the house",
            "station_order" => 1,
        ]);

        Station::create([
            "name" => "Head office",
            "station_order" => 1,
        ]);
    }
}
