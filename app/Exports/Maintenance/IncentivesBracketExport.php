<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class IncentivesBracketExport implements FromCollection, WithHeadings
{
    protected Collection $incentives_bracket;

    public function __construct(Collection $incentives_bracket)
    {
        $this->incentives_bracket = $incentives_bracket;
    }

    public function collection()
    {
        return $this->incentives_bracket->map(function ($unique_incentives_bracket) {
            return [
                $unique_incentives_bracket->incentives_bracket_id,
                $unique_incentives_bracket->bracket_name,
                $unique_incentives_bracket->month,
                $unique_incentives_bracket->amount,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Bracket ID',
            'Bracket Name',
            'Month',
            'Amount',
        ];
    }
}
