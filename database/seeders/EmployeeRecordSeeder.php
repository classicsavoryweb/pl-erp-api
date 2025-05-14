<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(EmployeeSeeder::class);
        $this->call(AgencyEmployeeSeeder::class);
        $this->call(BranchHistorySeeder::class);
        $this->call(PositionHistorySeeder::class);
        $this->call(ContractSeeder::class);
        $this->call(BankHistorySeeder::class);
    }
}
