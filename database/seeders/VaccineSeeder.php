<?php

namespace Database\Seeders;

use App\Models\Vaccine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VaccineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Vaccine::create([
            "description" => "SINOPHARM"
        ]);
        Vaccine::create([
            "description" => "SPUTNIK V"
        ]);
        Vaccine::create([
            "description" => "JANSSEN"
        ]);
        Vaccine::create([
            "description" => "PFIZER"
        ]);
        Vaccine::create([
            "description" => "MODERNA"
        ]);
        Vaccine::create([
            "description" => "ASTRAZENECA"
        ]);
        Vaccine::create([
            "description" => "SINOVAC"
        ]);
    }
}
