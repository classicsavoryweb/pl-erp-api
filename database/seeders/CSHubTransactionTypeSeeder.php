<?php

namespace Database\Seeders;

use App\Models\CSHubTransactionType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CSHubTransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/transaction_type.json');
        $jsonData = file_get_contents($jsonPath);
        $transactionTypes = json_decode($jsonData, true);

        foreach($transactionTypes["tbl_transaction_type"] as $transaction) {
            CSHubTransactionType::create([
                "description" => $transaction["description"],
                "ho_debit" => $transaction["ho_debit"],
                "ho_credit" => $transaction["ho_credit"],
                "branch_debit" => $transaction["branch_debit"],
                "branch_credit" => $transaction["branch_credit"],
                "status" => $transaction["status"],
            ]);
        }
    }
}
