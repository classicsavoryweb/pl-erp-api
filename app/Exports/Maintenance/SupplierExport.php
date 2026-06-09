<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class SupplierExport implements FromCollection, WithHeadings
{
    protected Collection $supplier;

    public function __construct(Collection $supplier)
    {
        $this->supplier = $supplier;
    }

    public function collection()
    {
        return $this->supplier->map(function ($supplier) {
            return [
                $supplier->vendor_number,
                $supplier->vendor_name,
               
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Vendor Number',
            'Vendor Name',
        ];
    }
}
