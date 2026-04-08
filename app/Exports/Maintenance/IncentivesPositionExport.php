<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class IncentivesPositionExport implements FromCollection, WithHeadings
{
    protected Collection $incentivePositions;

    public function __construct(Collection $incentivePositions)
    {
        $this->incentivePositions = $incentivePositions;
    }

    public function collection()
    {
        return $this->incentivePositions->map(function ($incentive) {
            return [
                $incentive->position->description,
                $incentive->amount,
                $incentive->oic_additional_amount,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Position',
            'Amount',
            'OIC Additional Amount',
        ];
    }
}
