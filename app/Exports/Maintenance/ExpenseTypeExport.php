<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ExpenseTypeExport implements FromCollection, WithHeadings
{
    protected Collection $expense_types;

    public function __construct(Collection $expense_types)
    {
        $this->expense_types = $expense_types;
    }

    public function collection()
    {
        return $this->expense_types->map(function ($et) {
            return [
                $et->description,
                $et->debit?->acc_name ?? $et->debit_id,
                $et->credit?->acc_name ?? $et->credit_id,
                ucfirst($et->status),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Debit Account',
            'Credit Account',
            'Status',
        ];
    }
}
