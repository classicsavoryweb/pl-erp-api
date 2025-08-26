<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AdditionalIncome;

class AdditionalIncomeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/additional_income.json');
        $jsonData = file_get_contents($jsonPath);
        $additional_income_details = json_decode($jsonData, true);
        $unique_additional_income_details = array_map("unserialize", array_unique(array_map("serialize", $additional_income_details["tbl_additionalincome"])));

        foreach ($unique_additional_income_details as $unique_additional_income_detail) {

            $taxable = ucfirst($unique_additional_income_detail["additionalincome_taxable"]);
            if ($taxable === 'N') {
                $taxable = 'No';
            } else {
                $taxable = 'Yes';
            }

            $status = ucfirst($unique_additional_income_detail["additionalincome_status"]);
            if ($status === 'Active') {
                $status = 'active';
            } else {
                $status = 'inactive';
            }

            AdditionalIncome::create([
                "description" => $unique_additional_income_detail["additionalincome_description"],
                "taxable" => $taxable,
                "status" => $status,
                "head_office_debit_id" => $unique_additional_income_detail["debit_ho"],
                "head_office_credit_id" => $unique_additional_income_detail["credit_ho"],
                "store_debit_id" => $unique_additional_income_detail["debit_store"],
                "store_credit_id" => $unique_additional_income_detail["credit_store"],
            ]);
        }
    }
}
