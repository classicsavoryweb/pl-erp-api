<?php

namespace App\Imports;

use App\Models\BiotimeLog;
use App\Models\Branch;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ActualLogsImport implements ToCollection, WithHeadingRow
{
    public function headingRow(): int
    {
        return 2; 
    }

    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            $punches = array_map("trim", explode(",",$row['time']));
            $date = $row['date'];
            $employee = Employee::where("biometric_number", $row["employee_id"])->first();
            // $branch = Branch::where("short_name", $row["department"])->first();
            $latest = BiotimeLog::where("employee_id", $employee->id)->orderBy("date", "DESC")->first();
            $parsedDate = Carbon::parse($punches[0]);
            $time = $parsedDate->toTimeString();

            if (Carbon::createFromTimeString($time)->lt(Carbon::createFromTimeString('04:00:00'))) {
                $latest->punches()->create([
                    "punch_time" => $punches[0],
                    "date" => $date,
                    "branch_id" => null
                ]);
            } else {
                $log = BiotimeLog::create([
                    "employee_id" => $employee->id,
                    "biometric_number" => $employee->biometric_number,
                    "date" => $date,
                ]);
                foreach ($punches as $punch) {
                    $log->punches()->create([
                        "punch_time" => $punch,
                        "date" => $date,
                        "branch_id" => null
                    ]);
                }
            }
        }
    }
}
