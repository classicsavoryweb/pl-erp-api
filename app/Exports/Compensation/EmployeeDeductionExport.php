<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class EmployeeDeductionExport implements FromCollection, WithHeadings
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
                $unique_deductions->employee->full_name ?? "",
                $unique_deductions->employee->employee_number ?? "",
                $unique_deductions->deduction->description ?? "",
                $unique_deductions->reference_doc,
                $unique_deductions->months,
                $unique_deductions->amount,
                $unique_deductions->source_of_fund,
                $unique_deductions->start_date,
                $unique_deductions->end_date,
                $unique_deductions->monthly_amortization,
                $unique_deductions->balance,
                $unique_deductions->remarks,
                $unique_deductions->debit_id,
                $unique_deductions->credit_id,
                optional($unique_deductions->branch)->name ?: '',
                $unique_deductions->block_payment,
                $unique_deductions->approved_by,
                $unique_deductions->approved_date,
                $unique_deductions->approved_status,
                $unique_deductions->deduction_ref,
                $unique_deductions->deduction_status,
                $unique_deductions->created_by,
                $unique_deductions->date_created,
                $unique_deductions->end_loan_reason,
                $unique_deductions->end_loan_status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Employee Number',
            'Description',
            'Reference Doc',
            'Month(s)',
            'Amount',
            'Source of Fund',
            'Start Date',
            'End Date',
            'Monthly Amortization',
            'Balance',
            'Remarks',
            'Debit Account',
            'Credit Account',
            'Branch',
            'Block Payment',
            'Approved By',
            'Approved Date',
            'Approved Status',
            'Deduction Ref',
            'Deduction Status',
            'Created By',
            'Date Created',
            'End Loan Reason',
            'End Loan Status',
        ];
    }
}
