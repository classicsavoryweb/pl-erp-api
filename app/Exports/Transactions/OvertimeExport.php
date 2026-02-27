<?php

namespace App\Exports\Transactions;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class OvertimeExport implements FromCollection, WithHeadings
{
    protected Collection $overtime;

    public function __construct(Collection $overtime)
    {
        $this->overtime = $overtime;
    }

    public function collection()
    {
        return $this->overtime->map(function ($unique_overtime) {
            return [
                $unique_overtime->date_filed,
                $unique_overtime->employee->full_name,
                $unique_overtime->employee->current_branch->name,
                $unique_overtime->employee->current_position->description,
                $unique_overtime->overtime_date,
                $unique_overtime->purpose,
                $unique_overtime->total_hours,
                floor($unique_overtime->hours_paid) ?? 0,
                $unique_overtime->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Date Filed',
            'Name',
            'Branch',
            'Position',
            'Overtime Date',
            'Purpose',
            'Total Hours',
            'Hours Paid',
            'Status',
        ];
    }
}
