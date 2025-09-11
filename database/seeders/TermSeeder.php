<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Term;

class TermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/terms.json');
        $jsonData = file_get_contents($jsonPath);
        $terms_details = json_decode($jsonData, true);
        $unique_terms_details = array_map("unserialize", array_unique(array_map("serialize", $terms_details["tbl_terms"])));

        foreach ($unique_terms_details as $unique_terms_detail) {
            Term::create([
                "description" => $unique_terms_detail["description"],
                "cutoff" => $unique_terms_detail["cutoff"],
                "amount_from" => $unique_terms_detail["amountfrom"],
                "amount_to" => $unique_terms_detail["amountto"],
                "month" => $unique_terms_detail["payroll_months"],
            ]);
        }
    }
}
