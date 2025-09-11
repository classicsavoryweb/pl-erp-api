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
                $unique_incentives_per_employee->amount,
                $unique_incentives_per_employee->max_amount
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Amount',
            'Max Amount',
        ];
    }
}
