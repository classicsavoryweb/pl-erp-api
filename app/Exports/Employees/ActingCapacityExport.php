<?php

namespace App\Exports\Employees;

use Carbon\Carbon;
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
                $employee->start_date ? Carbon::parse($employee->start_date)->format('F d, Y') : '',
                $employee->latest_end_date ? Carbon::parse($employee->latest_end_date)->format('F d, Y') : '',
                $employee->status,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Full Name', "Branch", "Current Position" , "Recommended Position", "Start Date", "End Date", "Status"];
    }
}
