<?php

namespace Database\Seeders;

use App\Models\AnnualTax;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AnnualTaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AnnualTax::create([
            "minimum_amount" => 0,
            "max_amount" => 250000,
            "tax_lower_limit" => 0,
            "percentage" => 0,
        ]);

        AnnualTax::create([
            "minimum_amount" => 250001,
            "max_amount" => 400000,
            "tax_lower_limit" => 0,
            "percentage" => 15,
        ]);

        AnnualTax::create([
            "minimum_amount" => 400001,
            "max_amount" => 800000,
            "tax_lower_limit" => 22500,
            "percentage" => 20,
        ]);

        AnnualTax::create([
            "minimum_amount" => 800001,
            "max_amount" => 2000000,
            "tax_lower_limit" => 102500,
            "percentage" => 25,
        ]);

        AnnualTax::create([
            "minimum_amount" => 2000001,
            "max_amount" => 8000000,
            "tax_lower_limit" => 402500,
            "percentage" => 30,
        ]);
    }
}
