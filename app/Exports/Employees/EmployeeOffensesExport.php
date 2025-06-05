<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeOffensesExport implements FromCollection, WithHeadings
{
    protected Collection $offenses;
    public function __construct(Collection $offenses)
    {
        $this->offenses = $offenses;
    }

    public function collection()
    {
        return $this->offenses->map(function ($offense) {
            return [
                $offense->employee->employee_number,
                $offense->employee->full_name,
                $offense->transgression,
                $offense->repetition,
                $offense->summary,
                $offense->offense->category,
                $offense->offense->subcategory,
                $offense->offense->description,
                $offense->sanction->description ?? "",
                $offense->date_committed,
                $offense->offense_action,
                $offense->date_served_ao,
                $offense->date_served_da,
                $offense->suspension_dates,
                $offense->remarks,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Full Name', "Transgression", "Repetition","Summary", "Category", "Sub Category" , "Description", "Sanction", "Date Committed", "Offense Action", "Date Served AO", "Date Served DA", "Remarks"];
    }
}
