<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class TimeKeepingSummaryReportExport implements FromCollection, WithHeadings
{
    protected Collection $timeKeepingSummaries;

    public function __construct(Collection $timeKeepingSummaries)
    {
        $this->timeKeepingSummaries = $timeKeepingSummaries;
    }

    public function collection()
    {
        return $this->timeKeepingSummaries->map(function ($tk) {
            return [
                $tk->last_name.", ".$tk->first_name." ".($tk->middle_name ?? ""),
                $tk->employee_number,
                $tk->employee_status,
                $tk->is_bank == 1 ? "BANK" : "CASH",
                $tk->is_on_maternity_leave == 1 ? "YES" : "NO",
                $tk->is_hold == 1 ? "YES" : "NO",
                $tk->has_complete_documents == 1 ? "YES" : "NO",
                $tk->current_branch_name,
                $tk->has_schedule == 1 ? "YES" : "NO",
                $tk->has_biotime == 1 ? "YES" : "NO",
                $tk->timekeeping_branch_name,
                $tk->regular_days
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Employee Name",
            "Employee Number",
            "Status",
            "Payment Type",
            "Maternity Status",
            "On Hold",
            "Complete Documents",
            "Current Branch",
            "Has Encoded Schedule",
            "Has Biotime Logs",
            "Timekeeping Branch",
            "Regular Days"
        ];
    }
}
