<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class RateExport implements FromCollection, WithHeadings
{
   protected Collection $rates;

    public function __construct(Collection $rates)
    {
        $this->rates = $rates;
    }

    public function collection()
    {
        return $this->rates->map(function ($rate) {
            return [
                $rate->code,
                $rate->minimum,
                $rate->area,
                $rate->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Code',
            'Minimum',
            'Area',
            'Status',
        ];
    }
}
