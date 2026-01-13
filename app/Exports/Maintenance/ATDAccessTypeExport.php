<?php

namespace App\Exports\Maintenance;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ATDAccessTypeExport implements FromCollection, WithHeadings
{
    protected Collection $records;

    public function __construct(Collection $records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records->map(function ($record) {
            return [
                $record->deduction?->description ?? "",
                $record->role?->name ?? "",
                $record->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Deduction",
            "Role",
            "Status",
        ];
    }
}
