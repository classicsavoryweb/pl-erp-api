<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Overtime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;

class OvertimeImport implements ToCollection, WithHeadingRow
{
    public function headingRow(): int
    {
        return 1; 
    }

    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            
            $employee = Employee::where("biometric_number", $row["biometrics_id"])
                ->where("status", "active")
                ->first();

            if (!$employee) {
                continue;
            }

            $employee_id = $employee->id;

            $biometric_number = $employee->biometric_number;

            $formattedDateFiled = is_numeric($row["date_filed"])
                ? Date::excelToDateTimeObject($row["date_filed"])->format('Y-m-d')
                : Carbon::createFromFormat("m-d-Y", $row["date_filed"])->format("Y-m-d");

            $formattedOvertimeDate = is_numeric($row["overtime_date"])
                ? Date::excelToDateTimeObject($row["overtime_date"])->format('Y-m-d')
                : Carbon::createFromFormat("m-d-Y", $row["overtime_date"])->format("Y-m-d");

            $existing = Overtime::where("biometrics_id", $biometric_number)
                ->where("overtime_date", $formattedOvertimeDate)
                ->where("date_filed", $formattedDateFiled)
                ->first();

            if ($existing) {
                continue;
            }

            $status = ucfirst($row["status"]);
            if ($status === 'Paid') {
                $status = 'Approved';
            } elseif ($status === 'Unpaid') {
                $status = 'Unapproved';
            }

            Overtime::create([
                "date_filed" => $formattedDateFiled,
                "employee_id"=> $employee_id,
                "biometrics_id" => $biometric_number,
                "overtime_date" => $formattedOvertimeDate,
                "purpose" => $row["purpose"],
                "total_hours" => $row["total_hours"],
                "status" => $status,
            ]);
        }
    }
}

