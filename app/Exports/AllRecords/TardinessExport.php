<?php

namespace App\Exports\AllRecords;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;


class TardinessExport implements FromCollection, WithHeadings
{
    protected Collection $tardiness;

    public function __construct(Collection $tardiness)
    {
        $this->tardiness = $tardiness;
    }

    public function collection()
    {
        return $this->tardiness->map(function ($record) {
            return [
                $record->employee->employee_number ?? "",
                $record->employee->full_name ?? "",
                optional($record->employee->current_branch)->short_name ?? "",
                optional($record->tardiness_sanction)->count ?? "",
                Carbon::create()->month($record->month)->format('F'), 
                $record->year,
                $record->total_minutes,
                $record->nte_status ?? "",
                $record->nte_date_sent ?? "",
                $record->da_status ?? "",
                $record->da_date_sent ?? "",
                $record->status ?? "",
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Employee Number",
            "Full Name",
            "Branch",
            "Count",
            "Month",
            "Year",
            "Total Minutes",
            "NTE Status",
            "NTE Date Sent",
            "NOD Status",
            "NOD Date Sent",
            "Status",
        ];
    }
}
