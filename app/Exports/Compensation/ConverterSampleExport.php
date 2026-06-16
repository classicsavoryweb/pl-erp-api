<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConverterSampleExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell
{
    protected Collection $transactions;

    public function __construct(Collection $transactions)
    {
        $this->transactions = $transactions;
    }

    public function title(): string {
        return "Converter Sample";
    }

    public function startCell(): string {
        return 'A6';
    }

    public function collection()
    {
        return $this->transactions->map(function ($transaction) {
            return [
                $transaction->bank_account_number ?? "",
                $transaction->amount,
                $transaction->employee->full_name,
                $transaction->transaction_type->description ?? "",
            ];
        });
    }

    public function headings(): array
    {
        return [
            "ACCOUNT #",
            "AMOUNT",
            "NAME",
            "REMARKS"
        ];
    }
}
