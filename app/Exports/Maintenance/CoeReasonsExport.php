<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class CoeReasonsExport implements FromCollection, WithHeadings
{
    protected Collection $reasons;

    public function __construct(Collection $reasons)
    {
        $this->reasons = $reasons;
    }

    public function collection()
    {
        return $this->reasons->map(function ($d) {
            return [
                $d->reason,
                $d->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Reason',
            'Status',
        ];
    }
}
