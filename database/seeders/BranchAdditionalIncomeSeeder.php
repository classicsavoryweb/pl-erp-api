<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchAdditionalIncome;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchAdditionalIncomeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/branch_additional_income.json');
        $jsonData = file_get_contents($jsonPath);
        $additionalIncome = json_decode($jsonData, true);

        foreach ($additionalIncome["tbl_additionalincome_hr_hist"] as $detail) {
            $branch = Branch::where("branch_number", $detail["branch_id2"])->first();

            if ($branch) {
                BranchAdditionalIncome::create([
                    "branch_id" => $branch->id,
                    "amount" => $detail["amount"],
                    "pay_date" => $detail["paydate"]
                ]);
            }
        }
    }
}
