<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class AdjustmentTypeExport implements FromCollection, WithHeadings
{
    protected Collection $adjustmentType;

    public function __construct(Collection $adjustmentType)
    {
        $this->adjustmentType = $adjustmentType;
    }

    public function collection()
    {
        return $this->adjustmentType->map(function ($data) {
            return [
                $data->description,
                $data->status,
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
