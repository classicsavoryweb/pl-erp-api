<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnduplicateEmployeeNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $duplicateEmployeeNumbers = Employee::select('employee_number_2')
        ->groupBy('employee_number_2')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('employee_number_2');

        $employees = Employee::whereIn('employee_number_2', $duplicateEmployeeNumbers)->get();

        $grouped = $employees->groupBy('employee_number_2');

        $final = collect();

        foreach ($grouped as $number => $group) {
            $allInactive = $group->every(fn ($emp) => $emp->status === "inactive");

            $group->values()->each(function ($emp, $index) use (&$final, $allInactive) {
                if ($emp->status === "inactive") {
                    if ($allInactive) {
                        $emp->employee_number_2 = $emp->employee_number_2 . '-D' . ($index + 1);
                        $emp->employee_number = $emp->employee_number . '-D' . ($index + 1);
                        $emp->biometric_number = $emp->biometric_number . '-D' . ($index + 1);
                    } else {
                        $emp->employee_number_2 = $emp->employee_number_2 . '-D';
                        $emp->employee_number = $emp->employee_number . '-D';
                        $emp->biometric_number = $emp->biometric_number . '-D';
                    }
                }
                $emp->save();
            });
        }
    }
}
