<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class DiagnosisExport implements FromCollection, WithHeadings
{
    protected Collection $diagnosis;

    public function __construct(Collection $diagnosis)
    {
        $this->diagnosis = $diagnosis;
    }

    public function collection()
    {
        return $this->diagnosis->map(function ($d) {
            return [
                $d->description,
                $d->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Status',
        ];
    }
}
