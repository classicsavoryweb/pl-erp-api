<?php

namespace App\Exports\Reports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeDeductionsReportExport implements FromCollection, WithHeadings
{
    
    protected Collection $deductions;

    public function __construct(Collection $deductions)
    {
        $this->deductions = $deductions;
    }

    public function collection()
    {
        return $this->deductions->map(function ($deduction) {
            return [
                $deduction->deduction->description,
                $deduction->actual_deduction->reference_doc,
                $deduction->employee->employee_number,
                $deduction->employee->full_name,
                $deduction->amount,
                $deduction->actual_deduction->remarks
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Description",
            "Reference Doc",
            "Employee Number",
            "Employee Name",
            "Deduction Amount",
            "Remarks"
        ];
    }
}
