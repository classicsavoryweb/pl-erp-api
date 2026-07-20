<?php

namespace App\Exports\Reports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class Bir2316Export implements FromCollection, WithHeadings
{
    protected Collection $records;

    public function __construct(Collection $records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records->map(function ($bir) {
            return [
                $bir->year,
                $bir->full_name(),
                $bir->employee->employee_number ?? '',
                $bir->schedule_type === 'schedule_1' ? 'Schedule 1 (Taxable)' : 'Schedule 2 (Non-Taxable)',
                $bir->tin,
                $bir->total_taxes_withheld,
                strtoupper($bir->status),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Year',
            'Employee',
            'Employee No.',
            'Schedule',
            'TIN',
            'Total Taxes Withheld',
            'Status',
        ];
    }
}
