<?php

namespace App\Exports\AllRecords;

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
        return $this->offenses->map(function ($offenses) {
            return [
                $offenses->employee->employee_number,
                $offenses->employee->full_name,
                $offenses->transgression,
                $offenses->repetition,
                $offenses->summary,
                $offenses->date_committed,
                $offenses->offense_action,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Full Name', "Transgression", "Repetition" , "Summary", "Date Committed", "Offense Action"];
    }
}
