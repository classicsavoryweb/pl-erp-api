<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeBranchesExport implements FromCollection, WithHeadings
{
    protected Collection $branches;
    public function __construct(Collection $branches)
    {
        $this->branches = $branches;
    }

    public function collection()
    {
        return $this->branches->map(function ($branch) {
            return [
                $branch->employee->employee_number,
                $branch->employee->full_name,
                $branch->branch->name,
                $branch->branch->branch_number,
                $branch->date_assigned,
                $branch->remarks,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Full Name', "Branch", "Branch Number" , "Date Assigned", "Remarks"];
    }
}
