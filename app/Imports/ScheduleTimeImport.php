<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\ScheduleTime;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
class ScheduleTimeImport implements ToCollection, WithHeadingRow
{
    public function headingRow(): int
    {
        return 2; 
    }

    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            $employee = Employee::where("biometric_number", $row["employee_id"])->first();

            $formattedDate = is_numeric($row["date"]) ? Date::excelToDateTimeObject($row["date"])->format('Y-m-d') : $row["date"];
            $checkIn = is_numeric($row["check_in"])
                ? Date::excelToDateTimeObject($row["check_in"])->format('H:i:s')
                : date('H:i:s', strtotime($row["check_in"]));

            $checkOut = is_numeric($row["check_out"])
                ? Date::excelToDateTimeObject($row["check_out"])->format('H:i:s')
                : date('H:i:s', strtotime($row["check_out"]));

            if ($employee) {
                ScheduleTime::create([
                    'employee_id' => $employee->id,
                    "biometric_number" => $row["employee_id"],
                    "date" => $formattedDate,
                    "timetable" => $row["timetable"],
                    "check_in" => $checkIn,
                    "check_out" => $checkOut,
                ]);
            }
        }
    }
}
