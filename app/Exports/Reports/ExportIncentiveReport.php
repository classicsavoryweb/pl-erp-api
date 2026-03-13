<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ExportIncentiveReport implements FromCollection, WithHeadings
{
    protected Collection $incentive;

    public function __construct(Collection $incentive)
    {
        $this->incentive = $incentive;
    }

    public function collection()
    {

        return $this->incentive->map(function ($attendance) {

            $days = json_decode($attendance->additional_days, true);

            $additionalDays = "";
            foreach ($days as $ad) {
                $additionalDays = $additionalDays. $ad['date']." : ".$ad['days']. " day | ";
            }

            return [
                $attendance->employee->employee_number,
                $attendance->employee->full_name,
                $attendance->employee->current_position->description,
                $attendance->employee->current_position->rank,
                $attendance->position->description,
                $attendance->position->rank,
                $attendance->employee->schedule->schedule_type->name,
                $attendance->employee->current_position->timekeeping_type,
                $attendance->amount_rate,
                $attendance->first_cut_off_worked_days,
                $attendance->second_cut_off_worked_days,
                $attendance->adjustment_days,
                $attendance->adjustment_percent,
                $attendance->incentive_amount,
                $attendance->remarks,
                $additionalDays
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Number',
            'Employee Name',
            'Current Position',
            "Current Rank",
            'Incentive Position',
            "Incentive Rank",
            "Schedule Type",
            "Payment Type",
            "Base Amount",
            '1st Cutoff Days',
            '2nd Cutoff Days',
            'Deduction Days',
            'Tardiness Deduction Percent',
            'Final Incentive Amount',
            'Remarks',
            'Additional Days Breakdown'
        ];

    }
}
