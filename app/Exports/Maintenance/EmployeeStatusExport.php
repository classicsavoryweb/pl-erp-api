<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class EmployeeStatusExport implements FromCollection, WithHeadings
{
    protected Collection $employeeStatus;

    public function __construct(Collection $employeeStatus)
    {
        $this->employeeStatus = $employeeStatus;
    }

    public function collection()
    {
        return $this->employeeStatus->map(function ($status) {
            return [
                $status->description,
                $status->type,
                $status->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Type',
            'Status',
        ];
    }
}
