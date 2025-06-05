<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class AgencyExport implements FromCollection, WithHeadings
{
    protected Collection $agencies;

    public function __construct(Collection $agencies)
    {
        $this->agencies = $agencies;
    }

    public function collection()
    {
        return $this->agencies->map(function ($agency) {
            return [
                $agency->name,
                $agency->address,
                $agency->employee_code,
                $agency->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Employee Code',
            'Address',
            'Status',
        ];
    }
}
