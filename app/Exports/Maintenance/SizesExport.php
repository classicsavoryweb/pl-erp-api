<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class SizesExport implements FromCollection, WithHeadings
{
    protected Collection $sizes;

    public function __construct(Collection $sizes)
    {
        $this->sizes = $sizes;
    }

    public function collection()
    {
        return $this->sizes->map(function ($sizes) {
            return [
                $sizes->description,
                $sizes->status,
               
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
