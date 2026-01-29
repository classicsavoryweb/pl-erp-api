<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PayrollListExport implements FromCollection, WithHeadings
{
    protected Collection $payrollList;

    public function __construct(Collection $payrollList)
    {
        $this->payrollList = $payrollList;
    }

    public function headings(): array
    {
        return [
            "Full Name",
            "Employee Number",
            "Status",
            "On Maternity Leave",
            "Mancom",
            "Payment Type",
            "On Hold",
            "Holding Date",
            "Bank Description",
            "Bank Number",
        ];
    }

    public function collection()
    {
        return $this->payrollList->map(function ($list) {
            
            $bank = $list->employee->latest_bank_details;

            return [
                $list->employee->full_name,
                $list->employee->employee_number,
                $list->is_included ? "Included" : "Excluded",
                $list->is_on_maternity_leave ? "Yes" : "No",
                $list->is_mancom ? "Yes" : "No",
                $list->is_bank ? "Bank" : "Cash",
                $list->is_hold ? "Yes" : "No",
                $list->holded_at ?? null,
                $bank?->bank_account_type?->description ?? null,
                $bank?->bank_number? "'" . $bank->bank_number: null,
           
            ];
        });
    }
}
