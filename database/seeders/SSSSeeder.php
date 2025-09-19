<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SSS;

class SSSSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/sss.json');
        $jsonData = file_get_contents($jsonPath);
        $sss_details = json_decode($jsonData, true);
        $unique_sss_details = array_map("unserialize", array_unique(array_map("serialize", $sss_details["tbl_sss"])));

        foreach ($unique_sss_details as $sss_detail) {
            SSS::create([
                "salary_range_min" => floatval(str_replace(',', '', $sss_detail["salary_range_min"])),
                "salary_range_max" =>floatval(str_replace(',', '', $sss_detail["salary_range_max"])),
                "salary_credit" => floatval(str_replace(',', '', $sss_detail["salary_credit"])),
                "employer_ss" => floatval(str_replace(',', '', $sss_detail["employer_ss"])),
                "employer_cc" => floatval(str_replace(',', '', $sss_detail["employer_ec"])),
                "employee_ss" => floatval(str_replace(',', '', $sss_detail["employee_ss"]))
            ]);
        }
    }
}
