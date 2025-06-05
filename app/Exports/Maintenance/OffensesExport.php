<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class OffensesExport implements FromCollection, WithHeadings
{
        protected Collection $offenses;

    public function __construct(Collection $offenses)
    {
        $this->offenses = $offenses;
    }

    public function collection()
    {
        return $this->offenses->map(function ($offense): array {
            return [
                $offense->category,
                $offense->subcategory,
                $offense->description,
                $offense->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Category',
            'Sub Category',
            'Description',
            'Status',
        ];
    }
}
