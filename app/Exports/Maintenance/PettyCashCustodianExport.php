<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PettyCashCustodianExport implements FromCollection, WithHeadings
{
    protected Collection $custodians;

    public function __construct(Collection $custodians)
    {
        $this->custodians = $custodians;
    }

    public function collection()
    {
        return $this->custodians->map(function ($custodian) {
            $branches = $custodian->branches->map(fn($br) => $br->name)->implode(', ');

            return [
                $custodian->user?->employee_number ?? 'N/A',
                $custodian->user
                    ? "{$custodian->user->last_name}, {$custodian->user->first_name} {$custodian->user->middle_name}"
                    : 'N/A',
                $branches,
                number_format($custodian->total_cash_fund, 2),
                ucfirst($custodian->status),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Number',
            'Employee Name',
            'Branches',
            'Total Cash Fund',
            'Status',
        ];
    }
}
