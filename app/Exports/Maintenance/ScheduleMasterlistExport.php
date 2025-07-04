<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ScheduleMasterlistExport implements FromCollection, WithHeadings
{
    protected Collection $sanctions;

    public function __construct(Collection $sanctions)
    {
        $this->sanctions = $sanctions;
    }

    public function collection()
    {
        return $this->sanctions->map(function ($sanction) {
            return [
                $sanction->start_time,
                $sanction->end_time,
                $sanction->selection,
                $sanction->classification,
                $sanction->time_break,
                $sanction->total_hours,
                $sanction->days,
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
            'Scheduled Hours',
            'Days'
        ];
    }
}
