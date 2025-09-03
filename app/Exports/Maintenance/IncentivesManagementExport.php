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
                $unique_incentives_management->amount,
                $unique_incentives_management->max_amount
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Amount',
            'Max Amount',
        ];
    }
}
