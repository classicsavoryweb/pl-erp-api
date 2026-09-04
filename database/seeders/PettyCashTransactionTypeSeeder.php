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
                'cash_fund_required' => false,
                'report_id_required' => true,
                'effect_direction'   => 'add',
                'allow_negative'     => false,
                'effect_target'      => 'remaining_cash_fund',
                'status'             => 'active',
            ],
            [
                'name'               => 'Additional Fund',
                'cash_fund_required' => true,
                'report_id_required' => false,
                'effect_direction'   => 'add',
                'allow_negative'     => false,
                'effect_target'      => 'both',
                'status'             => 'active',
            ],
            [
                'name'               => 'Adjustment',
                'cash_fund_required' => true,
                'report_id_required' => false,
                'effect_direction'   => 'both',
                'allow_negative'     => true,
                'effect_target'      => 'both',
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
