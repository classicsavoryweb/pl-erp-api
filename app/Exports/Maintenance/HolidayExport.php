<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class HolidayExport implements FromCollection, WithHeadings
{
    protected Collection $holidays;

    public function __construct(Collection $holidays)
    {
        $this->holidays = $holidays;
    }

    public function collection()
    {
        return $this->holidays->map(function ($holiday) {
            return [
                $holiday->date,
                $holiday->holiday_type,
                $holiday->description,
                $holiday->required_days,
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
