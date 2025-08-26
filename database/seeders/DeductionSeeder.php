<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Deduction;

class DeductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/deductions.json');
        $jsonData = file_get_contents($jsonPath);
        $deductions_details = json_decode($jsonData, true);
        $unique_deductions_details = array_map("unserialize", array_unique(array_map("serialize", $deductions_details["tbl_deduction"])));

        foreach ($unique_deductions_details as $unique_deductions_detail) {

            $status = ucfirst($unique_deductions_detail["status"]);
            if ($status === 'Active') {
                $status = 'active';
            } else {
                $status = 'inactive';
            }

            Deduction::create([
                "description" => $unique_deductions_detail["description"],
                "debit_id" => $unique_deductions_detail["debit_id"],
                "credit_id" => $unique_deductions_detail["credit_id"],
                "amortization_vendor_id" => $unique_deductions_detail["vendor_id"],
                "amortization_debit_id" => $unique_deductions_detail["debitamort_id"],
                "amortization_credit_id" => $unique_deductions_detail["creditamort_id"],
                "status" => $status,
                "end_loan_vendor_id" => $unique_deductions_detail["endloan_vendor"],
                "end_loan_debit_id" => $unique_deductions_detail["endloan_debitid"],
                "end_loan_credit_id" => $unique_deductions_detail["endloan_creditid"]
            ]);
        }
    }
}
