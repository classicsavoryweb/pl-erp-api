<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LastPay;
use App\Models\LastPayDeduction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LastPaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/last_pay.json');
        $jsonData = file_get_contents($jsonPath);
        $lastPays = json_decode($jsonData, true);

        $jsonPathDed = public_path('jsons/payroll/last_pay_deduction.json');
        $jsonDataDed = file_get_contents($jsonPathDed);
        $lastPaysDeds = json_decode($jsonDataDed, true);
        $deductions = $lastPaysDeds["tbl_lastpay_deduction"];

        foreach ($lastPays['tbl_lastpay'] as $lastPay) {

            $employee = Employee::where("employee_number", $lastPay["emp_id"])->first();

            if ($employee) {
                $lastPay = LastPay::create([
                    "last_pay_number" => $lastPay["lastpay_no"],
                    "employee_id" => $employee->id,
                    "payroll_hold" => str_replace(",", "",$lastPay["payroll_hold"]),
                    "thirteenth_month" => str_replace(",", "",$lastPay["13th_month"]),
                    "year" => str_replace(",", "",$lastPay["13th_year"]),
                    "sl_vl" => str_replace(",", "",$lastPay["slvl"]),
                    "tax_refund" => str_replace(",", "",$lastPay["tax_refund"]),
                    "incentives" => str_replace(",", "",$lastPay["incentives"]),
                    "total_deductions" => str_replace(",", "",$lastPay["total_deductions"]),
                    "sales" => str_replace(",", "",$lastPay["sales"]),
                    "meal" => str_replace(",", "",$lastPay["meal"]),
                    "last_pay" => str_replace(",", "",$lastPay["last_pay"]),
                    "status" => $lastPay["status"]
                ]);

                $employeeDeductions = array_filter($deductions, function ($d) use ($employee) {
                    return $d["emp_id"] === $employee->employee_number;
                });

                foreach ($employeeDeductions as $deduction) {
                    LastPayDeduction::create([
                        "last_pay_id" => $lastPay->id,
                        "employee_id" => $employee->id,
                        "reference_doc" => $deduction["reference_doc"],
                        "deduction_id" => str_replace("0", "", $deduction["deduction_id2"]),
                        "amount" => str_replace(",", "",$deduction["amount"]),
                    ]);
                }
            }
        }
    }
}
