<?php

namespace App\Exports\Reports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TimeKeepingProoflistReportExport implements FromCollection, WithHeadings
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
                $tk->cut_off ?? '',
                $tk->employee?->full_name ?? '',
                $tk->branch?->name ?? '',
                $tk->regular_days ?? 0,
                $tk->special_leave ?? 0,
                $tk->cancelled_off ?? 0,
                $tk->special_holiday ?? 0,
                $tk->legal_holiday ?? 0,
                $tk->regular_overtime ?? 0,
                $tk->night_differential ?? 0,
                $tk->night_differential_overtime ?? 0,
                $tk->legal_holiday_overtime ?? 0,
                $tk->special_holiday_overtime ?? 0,
                $tk->undertime ?? 0,
                $tk->tardiness ?? 0,
                $tk->travel_time ?? 0,
                $tk->adjustment_amount ?? 0,
                $tk->remarks ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Cutoff Range',
            'Employee Name',
            'Timekeeping Branch',
            'Regular Days',
            'Special Leave',
            'Cancelled Off',
            'Special Holiday',
            'Legal Holiday',
            'Regular Overtime',
            'Night Differential',
            'Night Differential Overtime',
            'Legal Holiday Overtime',
            'Special Holiday Overtime',
            'Undertime',
            'Tardiness',
            'Travel Time',
            'Taxable Adjustment',
            'Remarks',
        ];
    }
}
