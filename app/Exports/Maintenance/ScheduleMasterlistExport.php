<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ScheduleMasterlistExport implements FromCollection, WithHeadings
{
    protected Collection $schedule_masterlist;

    public function __construct(Collection $schedule_masterlist)
    {
        $this->schedule_masterlist = $schedule_masterlist;
    }

    public function collection()
    {
        return $this->schedule_masterlist->map(function ($schedule) {
            return [
                $schedule->start_time,
                $schedule->end_time,
                $schedule->selection,
                $schedule->classification,
                $schedule->time_break,
                $schedule->total_hours,
                $schedule->days,
                $schedule->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Start Time',
            'End Time',
            'Selection',
            'Classification',
            'Break Hours',
            'Total Hours',
            'Days',
            'Status'
        ];
    }
}
