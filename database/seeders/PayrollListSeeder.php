<?php

namespace Database\Seeders;

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

            PayrollList::create([
                "employee_id" => $employee->id,
                "is_included" => $employee->status === "active",
                "is_statutory_only" => $isMancom,
                "is_mancom" => $isMancom,
                "is_on_maternity_leave" => false
            ]);
        }
    }
}
