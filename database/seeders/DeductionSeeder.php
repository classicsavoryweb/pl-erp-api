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

            if ($unique_deductions_detail["description"] === "SSS Loans") {
                $category = "SSS";
            } else if (in_array($unique_deductions_detail["description"], ["Pag-ibig/HDMF Loans", "Pag-ibig MP2"])) {
                $category = "Pag-ibig";
            } else {
                $category = "Company";
            }

            Deduction::create([
                "description" => $unique_deductions_detail["description"],
                "category" => $category,
                "debit_id" => $unique_deductions_detail["debit_id"],
                "credit_id" => $unique_deductions_detail["credit_id"],
                "amortization_vendor_id" => $unique_deductions_detail["vendor_id"],
                "amortization_debit_id" => $unique_deductions_detail["debitamort_id"],
                "amortization_credit_id" => $unique_deductions_detail["creditamort_id"],
                "status" => $status,
                "end_loan_vendor_id" => $unique_deductions_detail["endloan_vendor"],
                "end_loan_debit_id" => $unique_deductions_detail["endloan_debitid"],
                "end_loan_credit_id" => $unique_deductions_detail["endloan_creditid"],
                "payslip_description" => $unique_deductions_detail["description"],
            ]);
        }

        $jsonPath1 = public_path('jsons/payroll/atd_maintenance_type.json');
        $jsonData1 = file_get_contents($jsonPath1);
        $deductions_details1 = json_decode($jsonData1, true);
        $unique_deductions_details1 = array_map("unserialize", array_unique(array_map("serialize", $deductions_details1["atd_mntn_type"])));

        foreach ($unique_deductions_details1 as $unique_deductions_detail) {
            $category = "Company";
            $other = Deduction::where("description", "Others")->first();
            $description = ucwords(strtolower($unique_deductions_detail["description"]));
            
            $isExisting = Deduction::where("description", $description)->first();

            if ($isExisting) {
                continue;
            }

            Deduction::create([
                "description" => $description,
                "category" => $category,
                "debit_id" => $other->debit_id,
                "credit_id" => $other->credit_id,
                "amortization_vendor_id" => $other->amortization_vendor_id,
                "amortization_debit_id" => $other->amortization_debit_id,
                "amortization_credit_id" => $other->amortization_credit_id,
                "status" => strtolower($unique_deductions_detail["status"]),
                "end_loan_vendor_id" => $other->end_loan_debit_id,
                "end_loan_debit_id" => $other->end_loan_debit_id,
                "end_loan_credit_id" => $other->end_loan_credit_id,
                "payslip_description" => $other->description,
            ]);
        }
    }
}
