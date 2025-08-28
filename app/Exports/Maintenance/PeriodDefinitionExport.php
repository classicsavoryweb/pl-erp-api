<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PeriodDefinitionExport implements FromCollection, WithHeadings
{
    protected Collection $period_definition;

    public function __construct(Collection $period_definition)
    {
        $this->period_definition = $period_definition;
    }

    public function collection()
    {
        return $this->period_definition->map(function ($unique_period_definition) {
            return [
                $unique_period_definition->month,
                $unique_period_definition->year,
                $unique_period_definition->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Month',
            'Year',
            'Status',
        ];
    }
}
