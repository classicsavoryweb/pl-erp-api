<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class VaccinneBrandExport implements FromCollection, WithHeadings
{
    protected Collection $vaccines;

    public function __construct(Collection $vaccines)
    {
        $this->vaccines = $vaccines;
    }

    public function collection()
    {
        return $this->vaccines->map(function ($vaccine) {
            return [
                $vaccine->description,
                $vaccine->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Status',
        ];
    }
}
