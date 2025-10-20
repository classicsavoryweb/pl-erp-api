<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class TardinessSanctionExport implements FromCollection, WithHeadings
{
    protected Collection $tardinessSanctions;

    public function __construct(Collection $tardinessSanctions)
    {
        $this->tardinessSanctions = $tardinessSanctions;
    }

    public function collection()
    {
        return $this->tardinessSanctions->map(function ($sanction) {
            return [
                $sanction->count,
                $sanction->punishment,
                $sanction->rank,
                $sanction->incentives_deduction,
                $sanction->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'count',
            'Punishment',
            'Rank',
            "Incentives Deduction",
            'Status',
        ];
    }
}
