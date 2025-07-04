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
        $this->call(EmployeeRequirementsSeeder::class);
        $this->call(BranchHistorySeeder::class);
        $this->call(PositionHistorySeeder::class);
        $this->call(ContractSeeder::class);
        $this->call(BankHistorySeeder::class);
        $this->call(EmployeeOffensesSeeder::class);
        $this->call(EmployeeTrainingSeeder::class);
        $this->call(TardinessSeeder::class);
        $this->call(TardinessMinutesSeeder::class);
        $this->call(VaccinationSeeder::class);
        $this->call(ActingCapacitySeeder::class);
        $this->call(AttachmentSeeder::class);
        $this->call(CoeRequestSeeder::class);
        $this->call(ScheduleSeeder::class);
        $this->call(UnduplicateEmployeeNumberSeeder::class);
    }
}
