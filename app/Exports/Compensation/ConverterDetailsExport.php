<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ConverterDetailsExport implements FromCollection, WithHeadings
{
    protected Collection $transactions;

    public function __construct(Collection $transactions)
    {
        $this->transactions = $transactions;
    }

    public function collection()
    {
        return $this->transactions->map(function ($transaction) {
            return [
                $transaction->employee->full_name,
                $transaction->employee->employee_number,
                $transaction->branch->name,
                $transaction->transaction_type->description ?? "",
                $transaction->transaction_number ?? "",
                $transaction->amount,
                $transaction->remarks,
                $transaction->source,
                $transaction->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Employee Number',
            'Branch',
            'Transaction Type',
            'Transaction Number',
            'Amount',
            'Remarks',
            'Source',
            'Status',
        ];
    }
}
