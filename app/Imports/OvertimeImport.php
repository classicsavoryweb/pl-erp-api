<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\TimeKeeping;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
class OvertimeImport implements ToCollection, WithHeadingRow
{
    public function headingRow(): int
    {
        return 2; 
    }

    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            $employee = Employee::where("employee_id", $row["employee_id"])->first();

            

            if ($employee) {
                TimeKeeping::create([
                    'employee_id' => $employee->id,
                    "biometric_number" => $employee->biometric_number,

                ]);
            }
        }
    }
}
