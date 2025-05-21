<?php

namespace Database\Seeders;

use App\Models\BankAccountType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(StationSeeder::class);
        $this->call(EmployeeStatusSeeder::class);
        $this->call(PositionSeeder::class);
        $this->call(AgencySeeder::class);
        $this->call(OffensesSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(DiagnosisSeeder::class);
        $this->call(SalaryPositionSeeder::class);
        $this->call(RateSeeder::class);
        $this->call(BankAccountTypeSeeder::class);
        $this->call(SanctionSeeder::class);
        $this->call(TrainingTitleSeeder::class);
        $this->call(VaccineSeeder::class);
        $this->call(CoeReasonSeeder::class);
    }
}
