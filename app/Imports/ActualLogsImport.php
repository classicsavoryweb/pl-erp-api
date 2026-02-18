<?php

namespace App\Imports;

use App\Models\BiotimeLog;
use App\Models\Branch;
use App\Models\Employee;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
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
            $employee = Employee::where("biometric_number", $row["employee_id"])->first();
            // $branch = Branch::where("short_name", $row["department"])->first();

            if ($employee) {
                $latest = BiotimeLog::where("employee_id", $employee->id)->orderBy("date", "DESC")->first();
                $parsedDate = Carbon::parse($punches[0]);
                $time = $parsedDate->toTimeString();
    
                $date = is_numeric($row["date"])
                    ? Date::excelToDateTimeObject($row["date"])->format('Y-m-d')
                    : date('Y-m-d', strtotime($row["date"]));
        

                $isExisting = BiotimeLog::where("employee_id", $employee->id)
                ->where("biometric_number", $employee->biometric_number)
                ->where("date", $date)
                ->get();

                if ($isExisting) {
                    foreach($isExisting as $record) {
                        $record->delete();
                        $record->punches()->delete();
                    }
                }

                $log = BiotimeLog::create([
                    "employee_id" => $employee->id,
                    "biometric_number" => $employee->biometric_number,
                    "date" => $date,
                ]);

                foreach ($punches as $punch) {
                    $newDate = $date;
                    if (Carbon::parse($punches[0])->gt(Carbon::parse($punch))) {
                        $newDate = Carbon::parse($date)->addDay()->format("Y-m-d");
                    }
                    $log->punches()->create([
                        "punch_time" => $newDate." ".$punch,
                        "date" => $date,
                        "branch_id" => null
                    ]);
                }
                $log->updateStatusFromPunchesCount(count($punches));
            }
        }
    }
}
