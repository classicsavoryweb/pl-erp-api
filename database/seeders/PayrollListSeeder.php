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
            PayrollList::create([
                "employee_id" => $employee->id,
                "is_included" => $employee->status === "active",
                "is_statutory_only" => false,
            ]);
        }
    }
}
