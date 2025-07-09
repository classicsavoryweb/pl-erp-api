<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class HolidayExport implements FromCollection, WithHeadings
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
                $sanction->date,
                $sanction->holiday_type,
                $sanction->description,
                $sanction->required_days,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Date',
            'Holiday Type',
            'Description',
            'Required Days',
        ];
    }
}
