<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\TravelOvertime;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class TravelTimeImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            $employee = Employee::where("employee_number", $row["employee_number"])->first();

            if (!$employee) {
                continue;
            }

            $formattedTravelTimeDate = is_numeric($row["date"])
                ? Date::excelToDateTimeObject($row["date"])->format('Y-m-d')
                : Carbon::createFromFormat("m-d-Y", $row["date"])->format("Y-m-d");


            TravelOvertime::updateOrCreate([
                "employee_id" => $employee->id,
                "date" => $formattedTravelTimeDate,
            ],[
                "travel_time" => $row["travel_time"],
                "remarks" => $row["remarks"] ?? null
            ]);
        }
    }
}
