<?php

namespace App\Exports\Compensation;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LastPayExport implements FromCollection, WithHeadings
{
    protected Collection $lastPays;

    public function __construct(Collection $lastPays)
    {
        $this->lastPays = $lastPays;
    }

    public function collection()
    {
        return $this->lastPays->map(function ($lastPay) {
            return [
                optional($lastPay->created_at)->format('Y-m-d'),
                $lastPay->last_pay_number,
                $lastPay->employee->full_name ?? '',
                $lastPay->employee->employee_number ?? '',
                $lastPay->employee->current_position->description ?? '',
                $lastPay->employee->current_branch->short_name ?? '',
                $lastPay->last_pay,
                $lastPay->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Date Created',
            'Last Pay Number',
            'Employee Name',
            'Employee Number',
            'Position',
            'Branch',
            'Last Pay',
            'Status',
        ];
    }
}
