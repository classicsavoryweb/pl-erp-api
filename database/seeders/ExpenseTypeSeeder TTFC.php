<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExpenseType;

class ExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['description' => 'Marketing Advertisement', 'debit_id' => '7000000', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Bank Charges', 'debit_id' => '7500001', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Deposit ', 'debit_id' => '7500001', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => '2GO', 'debit_id' => '7000002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Bus Cargo ', 'debit_id' => '7000002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Lalamove', 'debit_id' => '7000002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'LBC', 'debit_id' => '7000002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Other Courier', 'debit_id' => '7000002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Medical Supplies', 'debit_id' => '7500003', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Medicine', 'debit_id' => '7500003', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Drinking Water Refill', 'debit_id' => '8800002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Dry Ice', 'debit_id' => '8800002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Mancom Expenses', 'debit_id' => '8800002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Others (please specify)', 'debit_id' => '8800002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Photocopy & Printing', 'debit_id' => '8800002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Sample Items (Food & Non-Food)', 'debit_id' => '8800002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Training Food & Materils', 'debit_id' => '8800002', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Computer Accessories', 'debit_id' => '5000011', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Documentary Stamp', 'debit_id' => '8900003', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Notary ', 'debit_id' => '8900003', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Permits & Licenses', 'debit_id' => '8900003', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Professional Fee', 'debit_id' => '7500004', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Chemical Refill', 'debit_id' => '7000008', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Hauling Service', 'debit_id' => '7000008', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'On-call mechanic', 'debit_id' => '7000008', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Repair & Maintenance Materials', 'debit_id' => '7000008', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Water Testing', 'debit_id' => '7000008', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Salaries (HO)', 'debit_id' => '7000010', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Salaries (Branch)', 'debit_id' => '7000011', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Fuel', 'debit_id' => '7000018', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Parking Fee ', 'debit_id' => '7000018', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Store Visit Fare', 'debit_id' => '7000018', 'credit_id' => '4000000', 'status' => 'active'],
            ['description' => 'Transportation Fare ', 'debit_id' => '7000018', 'credit_id' => '4000000', 'status' => 'active'],
        ];

        foreach ($types as $type) {
            ExpenseType::updateOrCreate(
                ['description' => $type['description']],
                $type
            );
        }
    }
}
