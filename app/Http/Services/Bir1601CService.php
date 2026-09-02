<?php

namespace App\Http\Services;

use App\Models\Employee;
use App\Models\Payroll;
use Carbon\Carbon;

class Bir1601CService
{
    public function __construct(private PayrollService $payrollService)
    {
    }

    public function resolvePeriod(int $year, int $month): array
    {
        $end = Carbon::createFromDate($year, $month, 25)->startOfDay();
        $start = $end->copy()->subMonth()->day(26)->startOfDay();

        return [$start, $end];
    }

    public function computeSectionA(int $year, int $month): array
    {
        [$rangeStart, $rangeEnd] = $this->resolvePeriod($year, $month);

        $payrolls = Payroll::with(['employee.home_branch', 'employee.salary_position', 'employee.schedule.schedule_type', 'employee.payroll_list'])
            ->where('status', 'final')
            ->whereBetween('start_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get();

        $payrollGroups = $payrolls->groupBy('employee_id');

        $totalCompensation = 0.0;
        $mweBasicPay = 0.0;
        $mweHolidayOtNdf = 0.0;
        $eeContributions = 0.0;
        $taxableGrossNotSubjectToWtax = 0.0;
        $totalTaxesWithheld = 0.0;
        $thirteenthMonthPay = 0.0;

        foreach ($payrollGroups as $employeePayrolls) {
            $employee = $employeePayrolls->first()?->employee;

            if (!$employee) {
                continue;
            }

            $nonMaternityPayrolls = $employeePayrolls->filter(fn ($payroll) => !(bool) ($payroll->is_maternity ?? false));

            if ($nonMaternityPayrolls->isEmpty()) {
                continue;
            }

            $basicPay = $nonMaternityPayrolls->sum('salary')
                - $nonMaternityPayrolls->sum('undertime_amount')
                - $nonMaternityPayrolls->sum('tardiness_amount');
            $legalHolidayAmount = $nonMaternityPayrolls->sum('legal_holiday_amount')
                + $nonMaternityPayrolls->sum('special_holiday_amount');
            $overtimeAmount = $nonMaternityPayrolls->sum('overtime_amount')
                + $nonMaternityPayrolls->sum('legal_holiday_overtime_amount')
                + $nonMaternityPayrolls->sum('special_holiday_overtime_amount')
                + $nonMaternityPayrolls->sum('night_differential_overtime_amount')
                + $nonMaternityPayrolls->sum('cancelled_off')
                + $nonMaternityPayrolls->sum('taxable_adjustment');
            $nightDiffAmount = $nonMaternityPayrolls->sum('night_differential_amount');
            $holidayOtNdf = $legalHolidayAmount + $overtimeAmount + $nightDiffAmount;
            $withholdingTax = $nonMaternityPayrolls->sum('withholding_tax');
            $employeeContributions = $nonMaternityPayrolls->sum('employee_sss')
                + $nonMaternityPayrolls->sum('employee_philhealth')
                + $nonMaternityPayrolls->sum('employee_pagibig');
            $gross = $basicPay + $holidayOtNdf;

            $totalCompensation += $gross;
            $totalTaxesWithheld += $withholdingTax;
            $thirteenthMonthPay += $this->separatedThirteenthMonthPay($employee, $rangeEnd);

            $isMwe = $this->payrollService->isNcrMinimum($employee);

            if ($isMwe) {
                $mweBasicPay += $basicPay;
                $mweHolidayOtNdf += $holidayOtNdf;
                $eeContributions += $employeeContributions;
            } elseif ($withholdingTax > 0) {
                $eeContributions += $employeeContributions;
            } else {
                $taxableGrossNotSubjectToWtax += $gross - $employeeContributions;
            }
        }

        $statutoryMinimumWage = $mweBasicPay;
        $totalCompensation += $thirteenthMonthPay;

        return [
            'start_date' => $rangeStart->toDateString(),
            'end_date' => $rangeEnd->toDateString(),
            'total_compensation' => round($totalCompensation, 2),
            'statutory_minimum_wage' => round($statutoryMinimumWage, 2),
            'holiday_pay_ot_ndf' => round($mweHolidayOtNdf, 2),
            'thirteenth_month_pay' => round($thirteenthMonthPay, 2),
            'ee_contributions' => round($eeContributions, 2),
            'taxable_compensation_not_subject_to_wtax' => round($taxableGrossNotSubjectToWtax, 2),
            'total_taxes_withheld' => round($totalTaxesWithheld, 2),
        ];
    }

    private function separatedThirteenthMonthPay(Employee $employee, Carbon $rangeEnd): float
    {
        if (empty($employee->separation_date)) {
            return 0.0;
        }

        $separationDate = Carbon::parse($employee->separation_date);

        if ($separationDate->greaterThan($rangeEnd)) {
            return 0.0;
        }

        $yearHolded = (int) $separationDate->year;
        $dec11 = Carbon::create($yearHolded, 12, 11)->startOfDay();

        if ($separationDate->greaterThanOrEqualTo($dec11)) {
            $yearHolded += 1;
        }

        $periodStart = Carbon::create($yearHolded - 1, 12, 11)->format('Y-m-d');
        $periodEnd = Carbon::create($yearHolded, 12, 10)->format('Y-m-d');

        $annualSalary = Payroll::where('employee_id', $employee->id)
            ->where('status', 'final')
            ->whereBetween('end_date', [$periodStart, $periodEnd])
            ->get()
            ->sum(fn ($payroll) => ($payroll->salary ?? 0) - (($payroll->undertime_amount ?? 0) + ($payroll->tardiness_amount ?? 0)));

        return $annualSalary / 12;
    }

    public function computeTaxableCompensation(array $bir): array
    {
        $totalNonTaxable = (float) ($bir['statutory_minimum_wage'] ?? 0)
            + (float) ($bir['holiday_pay_ot_ndf'] ?? 0)
            + (float) ($bir['thirteenth_month_pay'] ?? 0)
            + (float) ($bir['ee_contributions'] ?? 0)
            + (float) ($bir['de_minimis_benefits'] ?? 0)
            + (float) ($bir['other_non_taxable_compensation'] ?? 0);

        $totalTaxableCompensation = (float) ($bir['total_compensation'] ?? 0) - $totalNonTaxable;
        $netTaxableCompensation = $totalTaxableCompensation - (float) ($bir['taxable_compensation_not_subject_to_wtax'] ?? 0);

        return [
            'total_taxable_compensation' => round($totalTaxableCompensation, 2),
            'net_taxable_compensation' => round($netTaxableCompensation, 2),
        ];
    }

    public function computeDerivedRemittance(array $bir): array
    {
        $addLessAdjustment = (float) ($bir['add_less_adjustment'] ?? 0);
        $totalTaxesWithheld = (float) ($bir['total_taxes_withheld'] ?? 0);

        if ($addLessAdjustment <= 0) {
            $taxStillDue = 0.0;
            $totalRemittance = $totalTaxesWithheld;
        } else {
            $totalRemittance = $totalTaxesWithheld + $addLessAdjustment;
            $taxStillDue = $totalRemittance;
        }

        return [
            'tax_still_due' => round($taxStillDue, 2),
            'total_remittance' => round($totalRemittance, 2),
            'total_amount_due' => round($totalRemittance, 2),
        ];
    }
}
