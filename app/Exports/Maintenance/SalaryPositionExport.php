<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class SalaryPositionExport implements FromCollection, WithHeadings
{
    protected Collection $salaryPosition;

    public function __construct(Collection $salaryPosition)
    {
        $this->salaryPosition = $salaryPosition;
    }

    public function collection()
    {
        return $this->salaryPosition->map(function ($position) {
            return [
                $position->position->description,
                $position->basic_salary,
                $position->rate_category,
                $position->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Position',
            'Basic Salary',
            'Rate Category',
            'Status',
        ];
    }
}
