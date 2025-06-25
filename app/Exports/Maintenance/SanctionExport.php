<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class SanctionExport implements FromCollection, WithHeadings
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
                $sanction->description,
                $sanction->suspension_days,
                $sanction->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Suspension Days',
            'Status',
        ];
    }
}
