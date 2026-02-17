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
                $days = $transaction->employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13;
                $regular_days = $transaction->timekeeping_summary->regular_days ?? "";
                $list = $transaction->employee->payroll_list;

                if ($list->isMancom() || $list->isOnMaternityLeave()) {
                    $regular_days = $days;
                }

                return [
                    $transaction->employee->employee_number,
                    $transaction->employee->full_name,
                    $transaction->timekeeping_branch->name ?? '',
                    $transaction->employee->latest_bank_details->bank_account_type->description ?? '', 
                    $transaction->cut_off,
                    round($transaction->daily_rate,2) ?? '',
                    round($transaction->hourly_rate,2) ?? '',
                    $transaction->tax_status ?? '',
                    $regular_days ?? '',
                    round($transaction->salary,2) ?? '',
                    $transaction->timekeeping_summary->cancelled_off_hours ?? '',
                    round($transaction->cancelled_off,2) ?? '',
                    $transaction->timekeeping_summary->special_holiday ?? '',
                    round($transaction->special_holiday_amount,2) ?? '',
                    $transaction->timekeeping_summary->legal_holiday ?? '',
                    round($transaction->legal_holiday_amount,2) ?? '',
                    $transaction->timekeeping_summary->regular_overtime ?? '',
                    round($transaction->overtime_amount,2) ?? '',
                    $transaction->timekeeping_summary->legal_holiday_overtime ?? '', 
                    round($transaction->legal_holiday_overtime_amount,2) ?? '',
                    $transaction->timekeeping_summary->special_holiday_overtime ?? '',
                    round($transaction->special_holiday_overtime_amount,2) ?? '',
                    $transaction->timekeeping_summary->night_differential ?? '',
                    round($transaction->night_differential_amount,2) ?? '',
                    $transaction->timekeeping_summary->night_differential_overtime ?? '',
                    round($transaction->night_differential_overtime_amount,2) ?? '',
                    $transaction->timekeeping_summary->tardiness ?? '',
                    round($transaction->tardiness_amount,2) ?? '',
                    $transaction->timekeeping_summary->undertime ?? '',
                    round($transaction->undertime_amount,2) ?? '',
                    round($transaction->taxable_adjustment,2) ?? '',
                    round($transaction->non_taxable_adjustment,2) ?? '',
                    round($transaction->gross_pay,2) ?? '',
                    round($transaction->employer_sss,2) ?? '',
                    round($transaction->employer_philhealth,2) ?? '',
                    round($transaction->employer_pagibig,2) ?? '',
                    round($transaction->employee_sss,2) ?? '',
                    round($transaction->employee_philhealth,2) ?? '',
                    round($transaction->employee_pagibig,2) ?? '',
                    round($transaction->taxable_income,2) ?? '',
                    round($transaction->withholding_tax,2) ?? '',
                    round($transaction->total_deductions,2) ?? '',
                    round($transaction->amount,2) ?? ''
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
                "Employee Number",
                "Full Name",
                "Branch",
                "Card Type", 
                "Cut Off",
                "Daily Rate",
                "Hourly Rate",
                "Status",
                "Regular Days",
                "Salary",
                "Cancelled Off Hours",
                "Cancelled Off Amount",
                "Special Holiday Hours",
                "Special Holiday Amount",
                "Legal Holiday Hours",
                "Legal Holiday Amount",
                "Overtime Regular Hours",
                "Overtimee Regular Amount",
                "Legal Holiday Overtime Hours",
                "Legal Holiday Overtime Amount",
                "Special Holiday Overtime Hours",
                "Special Holiday Overtime Amount",
                "Night Differential Hours",
                "Night Differential Amount",
                "Night Differential Overtime Hours",
                "Night Differential Overtime Amount",
                "Total Late Mins",
                "Total Late Amount",
                "Undertime Mins",
                "Undertime Amount",
                "Taxable Adjustments",
                "Non Taxable Adjustments",
                "Gross Pay",
                "ER SSS",
                "ER Philhealth",
                "ER Pagibig",
                "EE SSS",
                "EE Philhealth",
                "EE Pagibig",
                "Taxable Income",
                "Withholding Tax",
                "Total Deductions",
                "Net Pay"
            ];
        } else {
            return [
                "Full Name",
                "Employee Number",
                "Adjustment Percent",
                "Additional Incentive",
                "1st Cut Off Worked Days",
                "2nd Cut Off Worked Days",
                "Amount",
            ];
        }
    }
}
