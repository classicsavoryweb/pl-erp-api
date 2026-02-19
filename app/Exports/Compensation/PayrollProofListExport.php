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
                $salaryValue = $transaction->salary ?? null;
                $grossPayValue = $transaction->gross_pay ?? null;
                $taxableIncomeValue = $transaction->taxable_income ?? null;
                $netPayValue = $transaction->amount ?? null;

                if ($list && ($list->isMancom() || $list->isOnMaternityLeave())) {
                    $regular_days = $days;
                }

                if ($list && $list->isOnMaternityLeave()) {
                    $dailyRate = $transaction->daily_rate ?? null;
                    if (!$dailyRate || $dailyRate == 0) {
                        $dailyRate = $employee?->dailySalary() ?? 0;
                    }

                    $fullSalary = $dailyRate ? round($dailyRate * $days, 2) : 0;
                    if ($fullSalary == 0) {
                        $fullSalary = $employee?->cutOffBaseSalary() ?? 0;
                    }

                    if ($salaryValue === null || $salaryValue == 0) {
                        $salaryValue = $fullSalary;
                    }
                    if ($grossPayValue === null || $grossPayValue == 0) {
                        $grossPayValue = $fullSalary;
                    }
                    if ($taxableIncomeValue === null || $taxableIncomeValue == 0) {
                        $taxableIncomeValue = $fullSalary;
                    }
                    if ($netPayValue === null || $netPayValue == 0) {
                        $netPayValue = $fullSalary;
                    }
                }

                $branchName = $transaction->timekeeping_branch_name
                    ?? $transaction->timekeeping_branch?->name
                    ?? $employee?->current_branch?->name
                    ?? $employee?->home_branch?->name
                    ?? '';

                $branchCode = $transaction->timekeeping_branch_code
                    ?? $transaction->timekeeping_branch?->short_name
                    ?? $transaction->timekeeping_branch?->branch_number
                    ?? $employee?->current_branch?->short_name
                    ?? $employee?->current_branch?->branch_number
                    ?? $employee?->home_branch?->short_name
                    ?? $employee?->home_branch?->branch_number
                    ?? '';

                $bankType = $employee?->latest_bank_details?->bank_account_type?->description ?? '';

                $fmt = static function ($value) {
                    if ($value === null || $value === '') {
                        return '0';
                    }
                    return round($value, 2);
                };

                return [
                    $employee?->employee_number ?? '',
                    $employee?->full_name ?? '',
                    $branchName,
                    $branchCode,
                    $bankType,
                    $transaction->cut_off ?? '',
                    $fmt($transaction->daily_rate ?? null),
                    $fmt($transaction->hourly_rate ?? null),
                    $transaction->tax_status ?? '',
                    $fmt($regular_days ?? null),
                    $fmt($salaryValue),
                    $fmt($transaction->cancelled_off_hours ?? $transaction->timekeeping_summary?->cancelled_off_hours ?? null),
                    $fmt($transaction->cancelled_off ?? null),
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
                    $fmt($grossPayValue),
                    $fmt($transaction->employer_sss ?? null),
                    $fmt($transaction->employer_philhealth ?? null),
                    $fmt($transaction->employer_pagibig ?? null),
                    $fmt($transaction->employee_sss ?? null),
                    $fmt($transaction->employee_philhealth ?? null),
                    $fmt($transaction->employee_pagibig ?? null),
                    $fmt($taxableIncomeValue),
                    $fmt($transaction->withholding_tax ?? null),
                    $fmt($transaction->total_deductions ?? null),
                    $fmt($netPayValue)
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
