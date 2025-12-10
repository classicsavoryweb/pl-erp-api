<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PayrollProofListExport implements FromCollection, WithHeadings
{
    protected Collection $proofList;
    protected $type;

    public function __construct(Collection $proofList, $type = "PAYROLL")
    {
        $this->proofList = $proofList;
        $this->type = $type;
    }

    public function collection()
    {
        return $this->proofList->map(function ($transaction) {

            if ($this->type === "PAYROLL") {
                return [
                    $transaction->employee->full_name,
                    $transaction->employee->employee_number,
                    round($transaction->amount,2)
                ];
            } else {
                return [
                    $transaction->employee->full_name,
                    $transaction->employee->employee_number,
                    $transaction->adjustment_percent,
                    $transaction->additional_incentive,
                    $transaction->first_cut_off_worked_days,
                    $transaction->second_cut_off_worked_days,
                    round($transaction->amount,2),
                ];
            }
        });
    }

    public function headings(): array
    {

        if ($this->type === "PAYROLL") {
            return [
                "Full Name",
                "Employee Number",
                "Amount"
            ];
        } else {
            return [
                "Full Name",
                "Employee Number",
                "Adjustment Percent",
                "Additional Incentive",
                "1st Cut Off Worked Days",
                "2nd Cut Off Worked Days",
                "Amount"
            ];
        }
    }
}
