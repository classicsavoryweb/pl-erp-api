<?php

namespace App\Exports\Transactions;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class IncorrectLogExport implements FromCollection, WithHeadings
{
    protected Collection $incorrect_logs;

    public function __construct(Collection $incorrect_logs)
    {
        $this->incorrect_logs = $incorrect_logs;
    }

    public function collection()
    {
        return $this->incorrect_logs->map(function ($incorrect_log) {
            return [
                $incorrect_log->cutoff_period,
                $incorrect_log->employee->full_name ?? "-",
                $incorrect_log->employee->current_branch->name ?? "-",
                $incorrect_log->cancelled_rd,
                $incorrect_log->adjust_taxable,
                $incorrect_log->status,
                $incorrect_log->load_status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Cutoff',
            'Name',
            'Branch',
            'Cancelled RD',
            'Taxable Adjustment',
            'Status',
            'Payroll Status',
        ];
    }
}
