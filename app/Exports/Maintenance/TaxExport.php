<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class TaxExport implements FromCollection, WithHeadings
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
                $unique_tax->type,
                $unique_tax->status,
                $unique_tax->range_min,
                $unique_tax->range_max,
                $unique_tax->salary_base,
                $unique_tax->percentage,
                $unique_tax->exemption,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Type',
            'Status',
            'Range Minimum',
            'Range Maximum',
            'Salary Base',
            'Percentage',
            'Exemption',
        ];
    }
}
