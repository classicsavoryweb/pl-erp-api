<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tax;

class TaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/tax.json');
        $jsonData = file_get_contents($jsonPath);
        $tax_details = json_decode($jsonData, true);
        $unique_tax_details = array_map("unserialize", array_unique(array_map("serialize", $tax_details["tbl_tax"])));

        foreach ($unique_tax_details as $tax_detail) {
            Tax::create([
                "type" => $tax_detail["tax_type"],
                "status" => $tax_detail["tax_status"],
                "range_min" => floatval(str_replace(',', '', $tax_detail["tax_range_min"])),
                "range_max" => floatval(str_replace(',', '', $tax_detail["tax_range_max"])),
                "salary_base" => floatval(str_replace(',', '', $tax_detail["tax_salary_base"])),
                "percentage" => floatval(str_replace(',', '', $tax_detail["tax_percentage"])),
                "exemption" => floatval(str_replace(',', '', $tax_detail["tax_exemption"])),
            ]);
        }
    }
}
