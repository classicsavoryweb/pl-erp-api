<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class DeductionExport implements FromCollection, WithHeadings
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
                $unique_deductions->description,
                $unique_deductions->status,
                $unique_deductions->debit_id,
                $unique_deductions->credit_id,
                $unique_deductions->amortization_vendor_id,
                $unique_deductions->amortization_debit_id,
                $unique_deductions->amortization_credit_id,
                $unique_deductions->end_loan_vendor_id,
                $unique_deductions->end_loan_debit_id,
                $unique_deductions->end_loan_credit_id,
                $unique_deductions->payslip_description, 
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Status',
            'Debit Account',
            'Credit Account',
            'Amortization Vendor Account',
            'Amortization Debit Account',
            'Amortization Credit Account',
            'End Loan Vendor Account',
            'End Loan Debit Account',
            'End Loan Credit Account',
            'Pay Slip Description', 
        ];
    }
}
