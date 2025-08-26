<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class AdditionalIncomeExport implements FromCollection, WithHeadings
{
    protected Collection $additional_income;

    public function __construct(Collection $additional_income)
    {
        $this->additional_income = $additional_income;
    }

    public function collection()
    {
        return $this->additional_income->map(function ($unique_additional_income) {
            return [
                $unique_additional_income->description,
                $unique_additional_income->taxable,
                $unique_additional_income->status,
                $unique_additional_income->head_office_debit_id,
                $unique_additional_income->head_office_credit_id,
                $unique_additional_income->store_debit_id,
                $unique_additional_income->store_credit_id,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Taxable',
            'Status',
            'Head Office Debit Account',
            'Head Office Credit Account',
            'Store Debit Account',
            'Store Credit Account',
        ];
    }
}
