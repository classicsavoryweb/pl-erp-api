<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class BankAccountExport implements FromCollection, WithHeadings
{
    protected Collection $bankAccount;

    public function __construct(Collection $bankAccount)
    {
        $this->bankAccount = $bankAccount;
    }

    public function collection()
    {
        return $this->bankAccount->map(function ($bank) {
            return [
                $bank->description,
                $bank->number_length,
                $bank->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Number Length',
            'Status',
        ];
    }
}
