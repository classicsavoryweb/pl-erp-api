<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class SSSExport implements FromCollection, WithHeadings
{
    protected Collection $sss;

    public function __construct(Collection $sss)
    {
        $this->sss = $sss;
    }

    public function collection()
    {
        return $this->sss->map(function ($unique_sss) {
            return [
                $unique_sss->salary_range_min,
                $unique_sss->salary_range_max,
                $unique_sss->salary_credit,
                $unique_sss->employer_ss,
                $unique_sss->employer_cc,
                $unique_sss->employee_ss,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Salary Range Minimum',
            'Salary Range Maximum',
            'Salary Credit',
            'Employer SS',
            'Employer CC',
            'Employee SS',
        ];
    }
}
