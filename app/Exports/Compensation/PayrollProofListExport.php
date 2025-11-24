<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PayrollProofListExport implements FromCollection, WithHeadings
{
    protected Collection $proofList;

    public function __construct(Collection $proofList)
    {
        $this->proofList = $proofList;
    }

    public function collection()
    {
        return $this->proofList->map(function ($transaction) {
            return [
                $transaction->employee->full_name,
                $transaction->employee->employee_number,
                round($transaction->amount,2)
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Full Name",
            "Employee Number",
            "Amount"
        ];
    }
}
