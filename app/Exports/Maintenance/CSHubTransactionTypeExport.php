<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class CSHubTransactionTypeExport implements FromCollection, WithHeadings
{
    protected Collection $types;

    public function __construct(Collection $types)
    {
        $this->types = $types;
    }

    public function collection()
    {
        return $this->types->map(function ($type) {
            return [
                $type->description,
                $type->module->description,
                optional($type->deduction)->description ?: '',
                $type->trans_type->description,
                $type->unique_exam_type->description,
                $type->deduction_status_positive,
                $type->deduction_status_negative,
                $type->credit_status_positive,
                $type->credit_status_negative,
                $type->email_status,
                $type->ho_debit,
                $type->ho_credit,
                $type->branch_debit,
                $type->branch_credit,
                $type->advances_debit,
                $type->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'CS Hub Sourced Module',
            'Payroll Deduction ID',
            'CS Hub Transaction Type',
            'CS Hub Exam Type',
            'For Deduciton (Positive)',
            'For Deduction (Negative)',
            'For Credit (Positive)',
            'For Credit (Negative)',
            'For Email',
            'Advances Debit',
            'H.O. Debit',
            'H.O. Credit',
            'Branch Debit',
            'Branch Credit',
            'Status',
        ];
    }
}
