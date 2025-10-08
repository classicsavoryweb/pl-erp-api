<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PhilhealthPercentageBase;

class PhilhealthPercentageBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PhilhealthPercentageBase::create([
            "philhealth_formula" => "Percentage Based",
            "percentage" => 5,
            "philhealth_cap" => 10500,
            "philhealth_max" => 2500,
        ]);
    }
}
