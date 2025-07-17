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
                $unique_overtime->employee->full_name,
                $unique_overtime->biometric_number,
                $unique_overtime->employee->current_branch->name,
                $unique_overtime->date,
                $unique_overtime->regular_overtime,
                $unique_overtime->legal_holiday_overtime,
                $unique_overtime->special_holiday_overtime,
                $unique_overtime->night_diff_overtime,
                $unique_overtime->overtime_status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Biometric Number',
            'Branch',
            'Date',
            'Regular Overtime',
            'Legal Holiday Overtime',
            'Special Holiday Overtime',
            'Night Diff Overtime',
            'Overtime Status',
        ];
    }
}
