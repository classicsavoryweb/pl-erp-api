<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
class ReportIncentiveAttendanceExport implements FromCollection, WithHeadings
{
    protected Collection $attendanceReports;

    public function __construct(Collection $attendanceReports)
    {
        $this->attendanceReports = $attendanceReports;
    }

    public function collection()
    {

        return $this->attendanceReports->map(function ($attendance) {

            $days = json_decode($attendance->additional_days, true);

            $additionalDays = "";
            foreach ($days as $ad) {
                $additionalDays = $additionalDays. $ad['date']." : ".$ad['days']. " day | ";
            }

            return [
                $attendance->employee->employee_number,
                $attendance->employee->full_name,
                $attendance->branch->short_name,
                $attendance->employee->current_position->description,
                $attendance->employee->schedule->schedule_type->name,
                $attendance->employee->oic_status,
                $attendance->first_cut_off_worked_days,
                $attendance->second_cut_off_worked_days,
                $attendance->adjustment_days,
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
            'Timekeeping Branch',
            'Position',
            'Schedule Type',
            'OIC Status',
            '1st Cutoff Days',
            '2nd Cutoff Days',
            'Deduction Days',
            'Remarks',
            'Additional Days Breakdown'
        ];

    }
}
