<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Pagibig;

class PagibigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pagibig::create([
            "min_amount" => 1,
            "max_amount" => 1499,
            "percentage" => 1,
            "max_deduction" => 14.99,
        ]);

        Pagibig::create([
            "min_amount" => 1500,
            "max_amount" => 9999,
            "percentage" => 2,
            "max_deduction" => 199.98,
        ]);

        Pagibig::create([
            "min_amount" => 10000,
            "max_amount" => 100000,
            "percentage" => 2,
            "max_deduction" => 200,
        ]);
    }
}
