<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeContractsExport implements FromCollection, WithHeadings
{
    protected Collection $contracts;
    public function __construct(Collection $contracts)
    {
        $this->contracts = $contracts;
    }

    public function collection()
    {
        return $this->contracts->map(function ($contract) {
            return [
                $contract->employee->employee_number,
                $contract->employee->full_name,
                $contract->contract_number,
                $contract->contract_start_date,
                $contract->contract_end_date,
                $contract->remarks,
            ];
        });
    }

    public function headings(): array
    {
        return ['Employee Number','Full Name', "Contract Number", "Contract Start Date" , "Contract End Date", "Remarks"];
    }
}
