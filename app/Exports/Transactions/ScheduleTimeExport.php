<?php

namespace App\Exports\Transactions;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ScheduleTimeExport implements FromCollection, WithHeadings
{
    protected Collection $schedule_time;

    public function __construct(Collection $schedule_time)
    {
        $this->schedule_time = $schedule_time;
    }

    public function collection()
    {
        return $this->schedule_time->map(function ($schedule) {
            return [
                $schedule->employee->full_name,
                $schedule->biometric_number,
                $schedule->employee->current_branch->name,
                $schedule->date,
                $schedule->timetable,
                $schedule->check_in,
                $schedule->check_out,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Biometric Number',
            'Branch',
            'Date',
            'Timetable',
            'Check In',
            'Check Out'
        ];
    }
}
