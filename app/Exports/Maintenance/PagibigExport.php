<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PagibigExport implements FromCollection, WithHeadings
{
    protected Collection $pagibig;

    public function __construct(Collection $pagibig)
    {
        $this->pagibig = $pagibig;
    }

    public function collection()
    {
        return $this->pagibig->map(function ($unique_pagibig) {
            return [
                $unique_pagibig->employer_share,
                $unique_pagibig->employee_share,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employer Share',
            'Employee Share',
        ];
    }
}
