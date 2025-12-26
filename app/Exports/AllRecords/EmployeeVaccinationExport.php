<?php

namespace App\Exports\AllRecords;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeVaccinationExport implements FromCollection, WithHeadings
{
    protected Collection $vaccinations;

    public function __construct(Collection $vaccinations)
    {
        $this->vaccinations = $vaccinations;
    }

    public function collection()
    {
        return $this->vaccinations->map(function ($vaccination) {
            return [
                $vaccination->employee->employee_number ?? "",
                $vaccination->employee->full_name ?? "",
                $vaccination->vaccine->description ?? "",
                $vaccination->dose,
                $vaccination->date,
                $vaccination->status,
            ];
        });
    }

    public function headings(): array
    {
        return ["Employee Number", "Full Name", "Vaccine", "Dose", "Date", "Status"];
    }
}
