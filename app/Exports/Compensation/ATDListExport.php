<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ATDListExport implements FromCollection, WithHeadings
{
    protected Collection $deductions;

    public function __construct(Collection $deductions)
    {
        $this->deductions = $deductions;
    }

    public function collection()
    {
        return $this->deductions->map(function ($unique_deductions) {
            return [
                $unique_deductions->transaction_number,
                $unique_deductions->date_created,
                optional($unique_deductions->branch)->name ?: '',
                $unique_deductions->employee->full_name ?? "",
                $unique_deductions->deduction->description ?? "",
                $unique_deductions->reference_doc,
                $unique_deductions->months,
                $unique_deductions->amount,
                $unique_deductions->source_of_fund,
                $unique_deductions->monthly_amortization,
                $unique_deductions->balance,
                $unique_deductions->remarks,
                $unique_deductions->created_by,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Transaction Number',
            'Transaction Date',
            'ATD Branch',
            'Employee Name',
            'Description',
            'Reference Doc',
            'Month(s)',
            'Amount',
            'End Date',
            'Monthly Amortization',
            'Balance',
            'Remarks',
            'Created By',
        ];
    }
}
