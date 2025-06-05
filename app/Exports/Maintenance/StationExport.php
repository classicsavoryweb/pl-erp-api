<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class StationExport implements FromCollection, WithHeadings
{
    protected Collection $stations;

    public function __construct(Collection $stations)
    {
        $this->stations = $stations;
    }

    public function collection()
    {
        return $this->stations->map(function ($station) {
            return [
                $station->name,
                $station->station_order,
                $station->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Station Order',
            'Status',
        ];
    }
}
