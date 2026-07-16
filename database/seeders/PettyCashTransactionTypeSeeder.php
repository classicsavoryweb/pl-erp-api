<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PettyCashTransactionType;

class PettyCashTransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'               => 'Replenishment',
                'cash_fund_required' => true,
                'report_id_required' => false,
                'effect_direction'   => 'add',
                'effect_target'      => 'remaining_cash_fund',
                'status'             => 'active',
            ],
            [
                'name'               => 'Additional Fund',
                'cash_fund_required' => true,
                'report_id_required' => false,
                'effect_direction'   => 'add',
                'effect_target'      => 'both',
                'status'             => 'active',
            ],
            [
                'name'               => 'Withdrawal',
                'cash_fund_required' => true,
                'report_id_required' => true,
                'effect_direction'   => 'subtract',
                'effect_target'      => 'remaining_cash_fund',
                'status'             => 'active',
            ],
        ];

        foreach ($types as $type) {
            PettyCashTransactionType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
