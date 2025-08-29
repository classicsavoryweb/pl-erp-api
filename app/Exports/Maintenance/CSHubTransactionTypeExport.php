<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class CSHubTransactionTypeExport implements FromCollection, WithHeadings
{
    protected Collection $types;

    public function __construct(Collection $types)
    {
        $this->types = $types;
    }

    public function collection()
    {
        return $this->types->map(function ($type) {
            return [
                $type->description,
                $type->ho_debit,
                $type->ho_credit,
                $type->branch_debit,
                $type->branch_credit,
                $type->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'HO Debit',
            'HO Credit',
            'Branch Debit',
            'Branch Credit',
            'Status',
        ];
    }
}
