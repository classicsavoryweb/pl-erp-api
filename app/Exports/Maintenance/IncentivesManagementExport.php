<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class IncentivesManagementExport implements FromCollection, WithHeadings
{
    protected Collection $incentives_management;

    public function __construct(Collection $incentives_management)
    {
        $this->incentives_management = $incentives_management;
    }

    public function collection()
    {
        return $this->incentives_management->map(function ($unique_incentives_management) {
            return [
                $unique_incentives_management->employee->full_name,
                $unique_incentives_management->employee->employee_number,
                $unique_incentives_management->employee->current_position->description,
                $unique_incentives_management->amount,
                $unique_incentives_management->max_amount,
                $unique_incentives_management->employee->status,
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
