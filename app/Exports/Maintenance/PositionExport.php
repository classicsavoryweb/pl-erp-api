<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PositionExport implements FromCollection, WithHeadings
{
    protected Collection $positions;

    public function __construct(Collection $positions)
    {
        $this->positions = $positions;
    }

    public function collection()
    {
        return $this->positions->map(function ($position) {
            return [
                $position->description,
                $position->station->name,
                $position->rank,
                $position->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Station',
            'Rank',
            'Status',
        ];
    }
}
