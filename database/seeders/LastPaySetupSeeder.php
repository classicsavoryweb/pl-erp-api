<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LastPaySetup;

class LastPaySetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/last_pay_setup.json');
        $jsonData = file_get_contents($jsonPath);
        $last_pay_setup_details = json_decode($jsonData, true);
        $unique_last_pay_setup_details = array_map("unserialize", array_unique(array_map("serialize", $last_pay_setup_details["tbl_lastpay_setup"])));

        foreach ($unique_last_pay_setup_details as $unique_last_pay_setup_detail) {
            LastPaySetup::create([
                "debit_id" => $unique_last_pay_setup_detail["debit_acc"],
                "credit_id" => $unique_last_pay_setup_detail["credit_acc"],
                "setup_type" => $unique_last_pay_setup_detail["setuptype"],
                "vendor_id" => $unique_last_pay_setup_detail["vendor"],
            ]);
        }
    }
}
