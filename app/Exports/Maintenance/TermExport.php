<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class TermExport implements FromCollection, WithHeadings
{
    protected Collection $terms;

    public function __construct(Collection $terms)
    {
        $this->terms = $terms;
    }

    public function collection()
    {
        return $this->terms->map(function ($unique_terms) {
            return [
                $unique_terms->description,
                $unique_terms->cutoff,
                $unique_terms->amount_from,
                $unique_terms->amount_to,
                $unique_terms->month,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Cutoff',
            'Amount from',
            'Amount to',
            'Month',
        ];
    }
}
