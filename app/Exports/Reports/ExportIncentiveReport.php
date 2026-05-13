<?php

namespace App\Exports\Reports;

use Carbon\Carbon;
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
                Carbon::parse($attendance->employee->date_hired)->diffInMonths(Carbon::parse($attendance->created_at)),
                $attendance->employee->oic_status,
                $attendance->employee->current_position->rank,
                $attendance->position->description,
                $attendance->position->rank,
                $attendance->employee->schedule->schedule_type->name,
                $attendance->employee->current_position->timekeeping_type,
                $attendance->amount_rate,
                $attendance->base_additional_incentive,
                $attendance->first_cut_off_worked_days,
                $attendance->second_cut_off_worked_days,
                $attendance->adjustment_percent,
                $attendance->absent,
                $attendance->undertime_deduction_days,
                $attendance->adjustment_days,
                $attendance->added_days,
                $attendance->total_days,
                $attendance->additional_incentive,
                $attendance->incentive_amount,
                $additionalDays,
                $attendance->remarks
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Number',
            'Employee Name',
            'Current Position',
            'Tenure',
            'OIC Status',
            "Current Rank",
            'Incentive Position',
            "Incentive Rank",
            "Schedule Type",
            "Payment Type",
            "Base Amount",
            "Base Additional Incentive",
            '1st Cutoff Days',
            '2nd Cutoff Days',
            'Tardiness Deduction Percent',
            'Absent',
            'Undertime Deducted Days',
            'Total Deduction Days',
            'Days Added',
            'Total Days',
            'Additional Incentive',
            'Final Incentive Amount',
            'Additional Days Breakdown',
            'Remarks',
        ];

    }
}
