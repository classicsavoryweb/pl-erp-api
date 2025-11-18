<?php

namespace Database\Seeders;

use App\Models\BankDetail;
use App\Models\Employee;
use App\Models\PayrollList;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PayrollListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::all();

        foreach ($employees as $employee) {
            $isMancom = str_starts_with($employee->employee_number, 'M');

            $latestBank = $employee->latest_bank_details()->first();

            $isBank = $latestBank && $latestBank->bank_number !== '';

            PayrollList::create([
                "employee_id" => $employee->id,
                "is_included" => $employee->status === "active",
                "is_statutory_only" => $isMancom,
                "is_mancom" => $isMancom,
                "is_on_maternity_leave" => false,
                "is_bank" => $isBank
            ]);
        }
    }
}
