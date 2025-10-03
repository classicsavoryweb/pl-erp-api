<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\ScheduleTime;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ScheduleTimeImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected $excludedEmployeeIds;

    public function __construct(array $excludedEmployeeIds = [])
    {
        $this->excludedEmployeeIds = $excludedEmployeeIds;
    }

    public function headingRow(): int
    {
        return 1; 
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $collection)
    {
        $excludedCount = 0;
        $importedCount = 0;

        foreach ($collection as $row) {
            $employee = Employee::where("biometric_number", $row["employee_id"])
            ->where("status", "active")
            ->first();

            if ($employee && in_array($employee->biometric_number, $this->excludedEmployeeIds)) {
                $excludedCount++;
                continue;
            }

            $formattedDate = is_numeric($row["date"])
                ? Date::excelToDateTimeObject($row["date"])->format('Y-m-d')
                : date('Y-m-d', strtotime($row["date"]));

            $checkIn = is_numeric($row["check_in"])
                ? Date::excelToDateTimeObject($row["check_in"])->format('H:i:s')
                : date('H:i:s', strtotime($row["check_in"]));

            $checkOut = is_numeric($row["check_out"])
                ? Date::excelToDateTimeObject($row["check_out"])->format('H:i:s')
                : date('H:i:s', strtotime($row["check_out"]));

            $branch = Branch::where("short_name", $row["department"])->first();

            if ($employee) {
                $isExisting = ScheduleTime::where('biometric_number', $row["employee_id"])
                    ->whereDate('date', $formattedDate)
                    ->first();

                if ($isExisting) {
                    $isExisting->delete();
                }

                ScheduleTime::create([
                    'employee_id' => $employee->id,
                    'biometric_number' => $row["employee_id"],
                    'date' => $formattedDate,
                    'timetable' => $row["timetable"],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'branch_id' => $branch->id ?? $employee->branch_id
                ]);

                $importedCount++;
            }
        }
    }
}
