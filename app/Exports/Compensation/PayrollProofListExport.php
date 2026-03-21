<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Illuminate\Database\Eloquent\Collection;

class PayrollProofListExport implements FromCollection, WithHeadings, WithStrictNullComparison
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
        $rows = $this->proofList;

        return $rows->map(function ($transaction) {
            if ($this->type === "PAYROLL") {

                $fmt = static function ($value) {
                    if ($value === null || $value === '') {
                        return '0';
                    }
                    return round($value, 2);
                };

                return [
                    $transaction->employee_number ?? '',
                    trim(($transaction->last_name ?? '') . ", " . ($transaction->first_name ?? '') . " " . ($transaction->middle_name ?? '')),
                    $transaction->position ?? '',
                    $transaction->branch_name ?? '',
                    $transaction->short_name ?? '',
                    $transaction->current_bank_details->bank_account_type->description ?? '',
                    $transaction->cut_off ?? '',
                    $fmt($transaction->daily_rate ?? null),
                    $fmt($transaction->hourly_rate ?? null),
                    $transaction->tax_status ?? '',
                    $fmt($transaction->regular_days ?? null),
                    $fmt($transaction->salary),
                    $fmt($transaction->cancelled_off_hours ?? $transaction->timekeeping_summary?->cancelled_off_hours ?? null),
                    $fmt($transaction->cancelled_off_amount ?? null),
                    $fmt($transaction->special_holiday ?? $transaction->timekeeping_summary?->special_holiday ?? null),
                    $fmt($transaction->special_holiday_amount ?? null),
                    $fmt($transaction->legal_holiday ?? $transaction->timekeeping_summary?->legal_holiday ?? null),
                    $fmt($transaction->legal_holiday_amount ?? null),
                    $fmt($transaction->regular_overtime ?? $transaction->timekeeping_summary?->regular_overtime ?? null),
                    $fmt($transaction->overtime_amount ?? null),
                    $fmt($transaction->legal_holiday_overtime ?? $transaction->timekeeping_summary?->legal_holiday_overtime ?? null),
                    $fmt($transaction->legal_holiday_overtime_amount ?? null),
                    $fmt($transaction->special_holiday_overtime ?? $transaction->timekeeping_summary?->special_holiday_overtime ?? null),
                    $fmt($transaction->special_holiday_overtime_amount ?? null),
                    $fmt($transaction->night_differential ?? $transaction->timekeeping_summary?->night_differential ?? null),
                    $fmt($transaction->night_differential_amount ?? null),
                    $fmt($transaction->night_differential_overtime ?? $transaction->timekeeping_summary?->night_differential_overtime ?? null),
                    $fmt($transaction->night_differential_overtime_amount ?? null),
                    $fmt($transaction->tardiness ?? $transaction->timekeeping_summary?->tardiness ?? null),
                    $fmt($transaction->tardiness_amount ?? null),
                    $fmt($transaction->undertime ?? $transaction->timekeeping_summary?->undertime ?? null),
                    $fmt($transaction->undertime_amount ?? null),
                    $fmt($transaction->taxable_adjustment ?? null),
                    $fmt($transaction->non_taxable_adjustment ?? null),
                    $fmt($transaction->gross_pay ?? null),
                    $fmt($transaction->employer_sss ?? null),
                    $fmt($transaction->employer_philhealth ?? null),
                    $fmt($transaction->employer_pagibig ?? null),
                    $fmt($transaction->employee_sss ?? null),
                    $fmt($transaction->employee_philhealth ?? null),
                    $fmt($transaction->employee_pagibig ?? null),
                    $fmt($transaction->taxable_income ?? null),
                    $fmt($transaction->withholding_tax ?? null),
                    $fmt($transaction->total_deductions ?? null),
                    $fmt($transaction->net_pay ?? null)
                ];
            } else {
                return [
                    trim(($transaction->last_name ?? '') . ", " . ($transaction->first_name ?? '') . " " . ($transaction->middle_name ?? '')),
                    $transaction->employee_number ?? '',
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
                "Position",
                "Branch",
                "Branch Code",
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
