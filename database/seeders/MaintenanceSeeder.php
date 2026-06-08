<?php

namespace Database\Seeders;

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
        $this->call(RegionSeeder::class);
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
        $this->call(TardinessSanctionSeeder::class);
        $this->call(ActingCapacityReasonSeeder::class);
        $this->call(ScheduleTypeSeeder::class);
        $this->call(ScheduleMasterlistSeeder::class);
        $this->call(HolidaySeeder::class);
        $this->call(SSSSeeder::class);
        $this->call(TaxSeeder::class);
        $this->call(PhilhealthSeeder::class);
        $this->call(PagibigSeeder::class);
        $this->call(DeductionSeeder::class);
        $this->call(AdditionalIncomeSeeder::class);
        $this->call(PeriodDefinitionSeeder::class);
        $this->call(CSHubModulesSeeder::class);
        $this->call(CSHubSourceTypeSeeder::class);
        $this->call(CSHubTransactionTypeSeeder::class);
        $this->call(AdjustmentTypeSeeder::class);
        $this->call(PositionIncentivesSeeder::class);
        $this->call(IncentivesPerEmployeeSeeder::class);
        $this->call(TermSeeder::class);
        $this->call(LastPaySetupSeeder::class);
        $this->call(CutOffInitializeSeeder::class);
        $this->call(AnnualTaxSeeder::class);
        $this->call(UniformSizeSeeder::class);
        $this->call(UniformSupplierSeeder::class);
    }
}
