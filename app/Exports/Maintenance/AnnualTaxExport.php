<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AnnualTaxExport implements FromCollection, WithHeadings
{
    protected Collection $tax;

    public function __construct(Collection $tax)
    {
        $this->tax = $tax;
    }
    public function collection()
    {
        return $this->tax->map(function ($unique_tax) {
            return [
                $unique_tax->minimum_amount,
                $unique_tax->max_amount,
                $unique_tax->tax_lower_limit,
                $unique_tax->percentage,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Minimum Amount',
            'Maximum Amount',
            'Lower Limit',
            'Percentage',
        ];
    }
}
