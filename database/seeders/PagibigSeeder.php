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
        $jsonPath = public_path('jsons/payroll/pagibig.json');
        $jsonData = file_get_contents($jsonPath);
        $pagibig_details = json_decode($jsonData, true);
        $unique_pagibig_details = array_map("unserialize", array_unique(array_map("serialize", $pagibig_details["tbl_pagibigshare"])));

        foreach ($unique_pagibig_details as $pagibig_detail) {
            Pagibig::create([
                "employer_share" => $pagibig_detail["employer_share"],
                "employee_share" => $pagibig_detail["employee_share"],
            ]);
        }
    }
}
