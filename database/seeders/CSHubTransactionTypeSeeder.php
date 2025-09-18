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
            if ($transaction["trans_type"] == 9999) {
                $transaction["trans_type"] = '19';
            }

            if ($transaction["exam_type"] == 9999) {
                $transaction["exam_type"] = '19';
            }

            if ($transaction["deduction_id"] == 'N/A') {
                $transaction["deduction_id"] = '';
            }

            $deductionIdMap = [
                '00000011' => 10,
                '00000012' => 11,
                '00000013' => 12,
                '00000014' => 13,
                '00000015' => 14,
                '00000016' => 15,
                '00000017' => 16,
                '00000018' => 17,
                '00000019' => 18,
            ];

            if (array_key_exists($transaction["deduction_id"], $deductionIdMap)) {
                $transaction["deduction_id"] = $deductionIdMap[$transaction["deduction_id"]];
            } else {
                $transaction["deduction_id"] = ltrim($transaction["deduction_id"], '0');
            }

            CSHubTransactionType::create([
                "description" => $transaction["description"],
                "source_id" => $transaction["source"],
                "transaction_type" => $transaction["trans_type"],
                "exam_type" => $transaction["exam_type"],
                "deduction_id" => $transaction["deduction_id"],
                "deduction_status_positive" => $transaction["deduction_status_positive"],
                "deduction_status_negative" => $transaction["deduction_status_negative"],
                "credit_status_positive" => $transaction["credit_status_positive"],
                "credit_status_negative" => $transaction["credit_status_negative"],
                "email_status" => $transaction["email_status"],
                "ho_debit" => $transaction["ho_debit"],
                "ho_credit" => $transaction["ho_credit"],
                "branch_debit" => $transaction["branch_debit"],
                "branch_credit" => $transaction["branch_credit"],
                "advances_debit" => $transaction["advances_debit"],
                "status" => $transaction["status"],
            ]);
        }
    }
}
