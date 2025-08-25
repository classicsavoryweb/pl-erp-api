<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PhilhealthExport implements FromCollection, WithHeadings
{
    protected Collection $philhealth;

    public function __construct(Collection $philhealth)
    {
        $this->philhealth = $philhealth;
    }

    public function collection()
    {
        return $this->philhealth->map(function ($unique_philhealth) {
            return [
                $unique_philhealth->salary_range_min,
                $unique_philhealth->salary_range_max,
                $unique_philhealth->salary_base,
                $unique_philhealth->employee_share,
                $unique_philhealth->employer_share,
                $unique_philhealth->total_monthly_contribution,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Salary Range Minimum',
            'Salary Range Maximum',
            'Salary Base',
            'Employee Share',
            'Employer Share',
            'Total Monthly Contribution',
        ];
    }
}
