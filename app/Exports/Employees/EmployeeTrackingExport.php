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
                $employee->employee_number,
                $employee->employee_number_2,
                $employee->last_name,
                $employee->middle_name,
                $employee->first_name,
                $employee->email,
                $employee->current_position->description ?? '',
                $employee->current_branch->name ?? '',
                $employee->date_hired,
                $employee->employee_status->description ?? "",
                $employee->status,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Employee Number 2', 'Last Name', 'Middle Name', 'First Name', 'Email' ,'Position', 'Branch', 'Date Hired', 'Employment Status', 'Status',];
    }
}
