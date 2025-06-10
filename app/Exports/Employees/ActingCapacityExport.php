<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class ActingCapacityExport implements FromCollection, WithHeadings
{
    protected Collection $actingCapacity;
    public function __construct(Collection $actingCapacity)
    {
        $this->actingCapacity = $actingCapacity;
    }

    public function collection()
    {
        return $this->actingCapacity->map(function ($employee) {
            return [
                $employee->employee->employee_number,
                $employee->employee->full_name,
                $employee->employee->current_branch->name,
                $employee->current_position->description,
                $employee->recommended_position->description,
                $employee->status,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Full Name', "Branch", "Current Position" , "Recommended Position", "Status"];
    }
}
