<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Tardiness;
use App\Models\TardinessSanction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Log;
class TardinessImport implements ToCollection, WithHeadingRow
{

    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            if ($row["status"] === "FOR MEMO") {

                $employee = Employee::where("employee_number", $row["employee_id"])->first();
                $branch = $employee->current_branch()->first();

                $lastSanction = "";
                $lastTardiness = Tardiness::where("employee_id", $employee->id)
                    ->where("status", "Approved")
                    ->where("year", Carbon::now()->year)
                    ->latest()
                    ->first();

                if ($lastTardiness) {
                    $lastSanction = $lastTardiness->tardiness_sanction()->first();
                }
                
                if ($lastSanction) {
                    $rank = $lastSanction->rank !== 12 ? $lastSanction->rank + 1 : $lastSanction->rank;
                    $sanction = TardinessSanction::where("rank", $rank)->first();
                } else {
                    $sanction = TardinessSanction::where("rank", 1)->first();
                }

                $dateString = trim($row['paydate1'] !== " " ? $row['paydate1'] : $row['paydate2']);
                $date = Carbon::createFromFormat('m/d/Y', $dateString );
                $month = $date->month;
                $year = $date->year; 

                Tardiness::create([
                    'employee_id' => $employee->id,
                    "branch_id" => $branch->id,
                    "month" => $month,
                    "year" => $year,
                    "total_minutes" => $row["total_mins"],
                    "tardiness_sanction_id" => $sanction->id,
                    "offense_punishment" => $sanction->punishment,
                    "status" => "Pending",
                ]);
            }
        }
    }
}
