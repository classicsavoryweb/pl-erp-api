<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeTrainingsExport implements FromCollection, WithHeadings
{
    protected Collection $trainings;

    public function __construct(Collection $trainings)
    {
        $this->trainings = $trainings;
    }

    public function collection()
    {
        return $this->trainings->map(function ($training) {
            return [
                $training->employee->employee_number,
                $training->employee->full_name,
                $training->training_title,
                $training->speaker,
                $training->venue,
                $training->batch,
                $training->others,
                $training->date,
                $training->covered_date,
                $training->option,
                $training->score,
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Employee Number",
            "Full Name",
            "Training Title",
            "Speaker",
            "Venue",
            "Batch",
            "Others",
            "Date",
            "Covered Date",
            "Option",
            "Score",
        ];
    }
}
