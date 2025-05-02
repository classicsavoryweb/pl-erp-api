<?php

namespace Database\Seeders;

use App\Models\BankAccountType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BankAccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BankAccountType::create([
            "description" => "Cash Card",
            "number_length" => "16",
            "status" => "active"
        ]);
        BankAccountType::create([
            "description" => "Savings Account",
            "number_length" => "12",
            "status" => "active"
        ]);
    }
}
