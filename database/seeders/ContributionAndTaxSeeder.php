<?php

namespace Database\Seeders;

use App\Models\ContributionAndTax;
use Illuminate\Database\Seeder;

class ContributionAndTaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/contributions_and_tax_set-up.json');
        $jsonData = file_get_contents($jsonPath);
        $contributions_and_tax_details = json_decode($jsonData, true);
        $unique_contributions_and_tax_details = array_map("unserialize", array_unique(array_map("serialize", $contributions_and_tax_details["tbl_contribution_setup"])));

        foreach ($unique_contributions_and_tax_details as $unique_contributions_and_tax_detail) {
            ContributionAndTax::create([
                "vendor_id" => $unique_contributions_and_tax_detail["vendor_id"],
                "debit_id" => $unique_contributions_and_tax_detail["debit_id"],
                "credit_id" => $unique_contributions_and_tax_detail["credit_id"],
                "vendor_name" => $unique_contributions_and_tax_detail["vendor_name"]
            ]);
        }
    }
}
