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
        $rows = $this->proofList;
        if ($this->type === "PAYROLL") {
            $rows = $rows->unique("employee_id")->values();
        }

        return $rows->map(function ($transaction) {
            if ($this->type === "PAYROLL") {
                $employee = $transaction->employee;
                $scheduleName = $employee?->schedule?->schedule_type?->name ?? '';
                $days = $scheduleName === "Compressed Schedule" ? 11 : 13;

                $regular_days = $transaction->regular_days ?? $transaction->timekeeping_summary?->regular_days ?? "";
                $list = $employee?->payroll_list;

                if ($list && ($list->isMancom() || $list->isOnMaternityLeave())) {
                    $regular_days = $days;
                }

                $branchName = $transaction->timekeeping_branch_name
                    ?? $transaction->timekeeping_branch?->name
                    ?? '';

                $bankType = $employee?->latest_bank_details?->bank_account_type?->description ?? '';

                $fmt = static function ($value) {
                    return $value === null ? '' : round($value, 2);
                };

                return [
                    $employee?->employee_number ?? '',
                    $employee?->full_name ?? '',
                    $branchName,
                    $bankType,
                    $transaction->cut_off ?? '',
                    $fmt($transaction->daily_rate ?? null),
                    $fmt($transaction->hourly_rate ?? null),
                    $transaction->tax_status ?? '',
                    $regular_days ?? '',
                    $fmt($transaction->salary ?? null),
                    $transaction->cancelled_off_hours ?? $transaction->timekeeping_summary?->cancelled_off_hours ?? '',
                    $fmt($transaction->cancelled_off ?? null),
                    $transaction->special_holiday ?? $transaction->timekeeping_summary?->special_holiday ?? '',
                    $fmt($transaction->special_holiday_amount ?? null),
                    $transaction->legal_holiday ?? $transaction->timekeeping_summary?->legal_holiday ?? '',
                    $fmt($transaction->legal_holiday_amount ?? null),
                    $transaction->regular_overtime ?? $transaction->timekeeping_summary?->regular_overtime ?? '',
                    $fmt($transaction->overtime_amount ?? null),
                    $transaction->legal_holiday_overtime ?? $transaction->timekeeping_summary?->legal_holiday_overtime ?? '',
                    $fmt($transaction->legal_holiday_overtime_amount ?? null),
                    $transaction->special_holiday_overtime ?? $transaction->timekeeping_summary?->special_holiday_overtime ?? '',
                    $fmt($transaction->special_holiday_overtime_amount ?? null),
                    $transaction->night_differential ?? $transaction->timekeeping_summary?->night_differential ?? '',
                    $fmt($transaction->night_differential_amount ?? null),
                    $transaction->night_differential_overtime ?? $transaction->timekeeping_summary?->night_differential_overtime ?? '',
                    $fmt($transaction->night_differential_overtime_amount ?? null),
                    $transaction->tardiness ?? $transaction->timekeeping_summary?->tardiness ?? '',
                    $fmt($transaction->tardiness_amount ?? null),
                    $transaction->undertime ?? $transaction->timekeeping_summary?->undertime ?? '',
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
                    $fmt($transaction->amount ?? null)
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
