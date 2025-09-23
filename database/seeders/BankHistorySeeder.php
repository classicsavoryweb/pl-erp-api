<?php

namespace Database\Seeders;

use App\Models\BankAccountType;
use App\Models\BankDetail;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BankHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/bank_details.json');
        $jsonData = file_get_contents($jsonPath);
        $bank_details = json_decode($jsonData, true);

        foreach ($bank_details["tbl_bankdetails"] as $detail) {
            $employee = Employee::where("employee_number", $detail["emp_id"])->first();
            $bank = BankAccountType::where("description", $detail["account_id"] == "5" ? "Cash Card" : "Savings Account")->first();

            if ($employee) {
                BankDetail::create([
                    "employee_id" => $employee->id,
                    "bank_account_type_id" => $bank->id,
                    "bank_number" => $detail["bank_no"],
                    "remarks" => $detail["remarks"]
                ]);
            }
        }
    }
}
