<?php

namespace App\Exports\Transactions;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class TravelOvertimeExport implements FromCollection, WithHeadings
{
    protected Collection $travelOvertimes;

    public function __construct(Collection $travelOvertimes)
    {
        $this->travelOvertimes = $travelOvertimes;
    }

    public function collection() {
        return $this->travelOvertimes->map(function ($travel) {
            return [
                $travel->employee->full_name,
                $travel->employee->current_branch->name,
                $travel->employee->current_position->description,
                $travel->date,
                $travel->travel_time,
                $travel->remarks,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Current Branch',
            'Current Position',
            'Date',
            'Travel Time',
            'Remarks'
        ];
    }
}
