<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeePositionsExport implements FromCollection, WithHeadings
{
protected Collection $positions;

    public function __construct(Collection $positions)
    {
        $this->positions = $positions;
    }

    public function collection()
    {
        return $this->positions->map(function ($position) {
            return [
                $position->employee->employee_number,
                $position->employee->full_name,
                $position->position->description,
                $position->date_started,
                $position->oic_assignment,
                $position->date_appointed,
                $position->remarks,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Full Name', "Position", "Date Started", "OIC Assignment" , "Date Appointed", "Remarks"];
    }
}
