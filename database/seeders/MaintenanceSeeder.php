<?php

namespace Database\Seeders;

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
    }
}
