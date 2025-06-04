<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeTrackingExport implements FromCollection, WithHeadings
{
    protected Collection $employees;

    public function __construct(Collection $employees)
    {
        $this->employees = $employees;
    }

    public function collection()
    {
        return $this->employees->map(function ($employee) {
            return [
                $employee->full_name,
                $employee->status,
                $employee->current_position->description ?? '',
                $employee->current_branch->name ?? '',
                $employee->date_hired,
            ];
        });
    }

    public function headings(): array
    {
        return ['Name', 'Status', 'Position', 'Branch', 'Date Hired'];
    }
}
