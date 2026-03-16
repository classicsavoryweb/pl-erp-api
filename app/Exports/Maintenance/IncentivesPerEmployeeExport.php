<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class IncentivesPerEmployeeExport implements FromCollection, WithHeadings
{
    protected Collection $incentives_per_employee;

    public function __construct(Collection $incentives_per_employee)
    {
        $this->incentives_per_employee = $incentives_per_employee;
    }

    public function collection()
    {
        return $this->incentives_per_employee->map(function ($unique_incentives_per_employee) {
            return [
                $unique_incentives_per_employee->employee->full_name,
                $unique_incentives_per_employee->employee->employee_number,
                $unique_incentives_per_employee->employee->current_position->description,
                $unique_incentives_per_employee->amount,
                $unique_incentives_per_employee->max_amount,
                $unique_incentives_per_employee->employee->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Employee Number',
            'Current Position',
            'Amount',
            'Max Amount',
            'Status'
        ];
    }
}
