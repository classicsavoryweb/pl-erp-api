<?php

namespace App\Exports\Compensation;

use Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConverterDetailsExport implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $transactions;

    public function __construct(Collection $transactions)
    {
        $this->transactions = $transactions;
    }

    public function title(): string {
        return "Transactions";
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
                $transaction->admin_added->proper_full_name ?? "",
                $transaction->created_at,
                $transaction->converter_main->batch_number ?? "",
                $transaction->converter_main->status ?? "",
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
            'Added By',
            'Date Created',
            'Batch Number',
            'Batch Status',
        ];
    }
}
