<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Philhealth;

class PhilhealthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/philhealth.json');
        $jsonData = file_get_contents($jsonPath);
        $philhealth_details = json_decode($jsonData, true);
        $unique_philhealth_details = array_map("unserialize", array_unique(array_map("serialize", $philhealth_details["tbl_philhealthcompute"])));

        foreach ($unique_philhealth_details as $philhealth_detail) {
            Philhealth::create([
                "salary_range_min" => floatval(str_replace(',', '', $philhealth_detail["salary_range_from"])),
                "salary_range_max" => floatval(str_replace(',', '', $philhealth_detail["salary_range_to"])),
                "salary_base" => floatval(str_replace(',', '', $philhealth_detail["salary_base"])),
                "employee_share" => floatval(str_replace(',', '', $philhealth_detail["employee_share"])),
                "employer_share" => floatval(str_replace(',', '', $philhealth_detail["employer_share"])),
                "total_monthly_contribution" => floatval(str_replace(',', '', $philhealth_detail["total_monthly_premium"]))
            ]);
        }
    }
}
