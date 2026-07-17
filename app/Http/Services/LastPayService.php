<?php
namespace App\Http\Services;

use App\Models\AnnualTax;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Incentives;
use App\Models\LastPay;
use App\Models\LastPayDeduction;
use App\Models\Payroll;
use Carbon\Carbon;

class LastPayService {
    public function computeLastPay(Employee $employee, $lastPayDetails) {
        $totalPayroll = $lastPayDetails->total_payroll;
        $month13th = $lastPayDetails->thirteenth_month;
        $incentives = $lastPayDetails->incentives;
        $taxRefund = $lastPayDetails->tax_refund;
        $sl_vl = $lastPayDetails->sl_vl;
        $meal_allowance = $lastPayDetails->meal_allowance;
        $sales = $lastPayDetails->sales;
        $year = $lastPayDetails->year;
        $additional_fields = $lastPayDetails->additional_fields;
        $totalDeductions = 0;
        $additionalFieldsTotal = 0;

        $companyDeductions = $lastPayDetails->company_deductions;
        $governmentDeductions = $lastPayDetails->government_deductions;

        $totalDeductions = $companyDeductions["total_deductions"];

        foreach ($additional_fields as $additional) {
            $additionalFieldsTotal += $additional["amount"];
        }

        $lastPayAmount = ($totalPayroll + $month13th + $incentives + $sl_vl + $meal_allowance + $taxRefund + $sales + $additionalFieldsTotal) - $totalDeductions;

        return [
            "company_deductions" => $companyDeductions["deductions"],
            "government_deductions" => $governmentDeductions["deductions"],
            "payroll_hold" => $totalPayroll,
            "thirteenth_month" => round($month13th, 2),
            "sales" => $sales,
            "incentives" => $incentives,
            "sl_vl" => $sl_vl,
            "meal_allowance" => $meal_allowance,
            "year" => $year,
            "tax_refund" => $taxRefund,
            "total_deductions" => $totalDeductions,
            "last_pay" => round($lastPayAmount, 2),
            "date_holded" => $employee->separation_date,
            "additional_fields" => $additional_fields,
            "remarks" => $lastPayDetails->remarks ?? null,
        ];
    }

    public function getDeductions(Employee $employee, $deductionType = []) {
        $deductionsArray = [];

        $deductions = EmployeeDeduction::with("deduction")
        ->where("employee_id", $employee->id)
        ->where("balance", ">=", 0.1    )
        ->where("approved_status", "Yes")
        ->where("end_loan_status", "No")
        ->whereHas("deduction", function ($query) use ($deductionType) {
            $query->whereIn("category", $deductionType);
        });

        $deductionsCollections = $deductions->get();

        foreach ($deductionsCollections as $deduction) {
            $deductionsArray[] = [
                "deduction_type" => $deduction->deduction->description,
                "employee_deduction_id" => $deduction->id,
                "balance" => $deduction->balance,
                "amount" => $deduction->balance,
                "category" => $deduction->deduction->category,
            ];
        }

        return [
            "deductions" => $deductionsArray,
            "total_deductions" => $deductions->sum("balance")
        ];
    }

    public function getAllHoldedPayrollsAmount (Employee $employee, $payDate) {
        $holdedPayrolls = Payroll::where("employee_id", $employee->id)
            ->where("pay_date", ">=" ,$payDate)
            ->sum("net_pay");

        return round($holdedPayrolls, 2) ?? 0;
    }

    public function getAllHoldedIncentivesAmount (Employee $employee, $payDate) {
        $holdedIncentives = Incentives::where("employee_id", $employee->id)
            ->where("pay_date", ">=" ,$payDate)
            ->where("status", "final")
            ->sum("incentive_amount");

        return round($holdedIncentives, 2) ?? 0;
    }

    public function getThirteenthMonthPayAmount (Employee $employee) {
        $annualSalary = 0;
        $payrolls = $this->getThirteenthMonthPayrollRows($employee);

        foreach ($payrolls as $payroll) {
            $salary = $this->numeric($payroll->salary) - ($this->numeric($payroll->undertime_amount) + $this->numeric($payroll->tardiness_amount));
            $annualSalary += $salary;
        }

        return $annualSalary > 0 ? round(($annualSalary / 12), 2) : 0;
    }

    public function getThirteenthMonthPayrollRows(Employee $employee)
    {
        $source = $employee->separation_date ? Carbon::parse($employee->separation_date) : Carbon::now();
        $yearHolded = (int) $source->year;
        $dec11 = Carbon::create($yearHolded, 12, 11)->startOfDay();
        if ($source->greaterThanOrEqualTo($dec11)) {
            $yearHolded += 1;
        }

        $thirteenthMonthPeriodStart = Carbon::create($yearHolded - 1, 12, 11)->format("Y-m-d");
        $thirteenthMonthPeriodEnd = Carbon::create($yearHolded, 12, 10)->format("Y-m-d");

        return Payroll::where("employee_id", $employee->id)
            ->where("status", "final")
            ->whereBetween("end_date", [$thirteenthMonthPeriodStart, $thirteenthMonthPeriodEnd])
            ->orderBy("end_date")
            ->get([
                "id",
                "start_date",
                "end_date",
                "salary",
                "tardiness_amount",
                "undertime_amount",
                "daily_rate",
                "net_pay",
            ]);
    }

    public function getLastPayTaxRefund (Employee $employee) {
        $source = $employee->separation_date ? Carbon::parse($employee->separation_date) : Carbon::now();
        $year = (int) $source->year;
        $taxRefund = 0;

        $start = Carbon::create($year - 1, 12, 26)->format("Y-m-d");
        $end = Carbon::create($year, 12, 25)->format("Y-m-d");

        $payrolls = Payroll::where("employee_id", $employee->id)
        ->where("status", "final")
        ->whereBetween("end_date", [$start, $end]);
        
        $annualTaxableIncome = $payrolls->sum("taxable_income");
        $totalWithheldTax = $payrolls->sum("withholding_tax");

        $annualTax = AnnualTax::where("minimum_amount", "<=" , $annualTaxableIncome)
            ->where("max_amount", ">=" , $annualTaxableIncome)
            ->first();

        if ($annualTax) {
            $amountShouldBePaid = $this->numeric($annualTax->tax_lower_limit) + 
                (($annualTaxableIncome - $this->numeric($annualTax->minimum_amount)) * (0.01 * $this->numeric($annualTax->percentage)));
            $taxRefund = $amountShouldBePaid < $totalWithheldTax ? $totalWithheldTax - $amountShouldBePaid : 0;
        }

        return round($taxRefund, 2);
    }

    public function getAdditionalFields(Employee $employee): array {
        $additionalFields = [];
        $lastPay = LastPay::with("additional_fields")
        ->where("employee_id", $employee->id)
        ->first();

        if ($lastPay) {
            // foreach () {

            // }
            $additionalFields = $lastPay->additional_fields;
        }

        return $additionalFields;
    }

    public function applyPostedLastPayDeductionBalances(LastPay $lastPay): void
    {
        $lastPayDeductions = LastPayDeduction::where("last_pay_id", $lastPay->id)
            ->where("employee_id", $lastPay->employee_id)
            ->get();

        foreach ($lastPayDeductions as $lastPayDeduction) {
            $employeeDeduction = EmployeeDeduction::where("employee_id", $lastPayDeduction->employee_id)
                ->where("reference_doc", $lastPayDeduction->reference_doc)
                ->lockForUpdate()
                ->first();

            if (!$employeeDeduction) {
                continue;
            }

            $currentBalance = $this->numeric($employeeDeduction->balance);
            $deductionAmount = $this->numeric($lastPayDeduction->amount);

            $employeeDeduction->update([
                "balance" => max(0, $currentBalance - $deductionAmount),
            ]);
        }
    }

    public function buildTaxRefundComputation(LastPay $lastPay): array
    {
        $lastPay->loadMissing(["employee"]);

        $year = (int) $lastPay->year;
        if (!$year && $lastPay->employee?->separation_date) {
            $source = Carbon::parse($lastPay->employee->separation_date);
            $year = (int) $source->year;
            if ($source->greaterThanOrEqualTo(Carbon::create($year, 12, 11)->startOfDay())) {
                $year += 1;
            }
        }

        if (!$year) {
            $year = (int) Carbon::now()->year;
        }

        $taxRefundPeriod = $this->getTaxRefundPeriod($year);
        $periodStart = $taxRefundPeriod["start"];
        $periodEnd = $taxRefundPeriod["end"];

        $payrolls = Payroll::where("employee_id", $lastPay->employee_id)
            ->where("status", "final")
            ->whereBetween("end_date", [$periodStart, $periodEnd])
            ->orderBy("end_date")
            ->get();

        $rows = $payrolls
            ->groupBy(fn ($payroll) => Carbon::parse($payroll->end_date)->format("Y-m"))
            ->map(function ($items, $monthKey) {
                return [
                    "month" => Carbon::createFromFormat("Y-m", $monthKey)->format("F"),
                    "code" => $items->first()?->tax_status,
                    "regular_gross" => round($items->sum(fn ($payroll) => $this->numeric($payroll->gross_pay)), 2),
                    "sss" => round($items->sum(fn ($payroll) => $this->numeric($payroll->employee_sss)), 2),
                    "phic" => round($items->sum(fn ($payroll) => $this->numeric($payroll->employee_philhealth)), 2),
                    "hdmf" => round($items->sum(fn ($payroll) => $this->numeric($payroll->employee_pagibig)), 2),
                    "taxable_income" => round($items->sum(fn ($payroll) => $this->numeric($payroll->taxable_income)), 2),
                    "tax_withheld" => round($items->sum(fn ($payroll) => $this->numeric($payroll->withholding_tax)), 2),
                ];
            })
            ->values();

        $totals = [
            "regular_gross" => round($rows->sum("regular_gross"), 2),
            "sss" => round($rows->sum("sss"), 2),
            "phic" => round($rows->sum("phic"), 2),
            "hdmf" => round($rows->sum("hdmf"), 2),
            "taxable_income" => round($rows->sum("taxable_income"), 2),
            "tax_withheld" => round($rows->sum("tax_withheld"), 2),
        ];

        $annualTax = AnnualTax::where("minimum_amount", "<=", $totals["taxable_income"])
            ->where("max_amount", ">=", $totals["taxable_income"])
            ->first();

        $taxDue = 0;
        if ($annualTax) {
            $taxDue = $this->numeric($annualTax->tax_lower_limit)
                + (($totals["taxable_income"] - $this->numeric($annualTax->minimum_amount)) * (0.01 * $this->numeric($annualTax->percentage)));
        }

        $computedRefund = max($totals["tax_withheld"] - $taxDue, 0);

        return [
            "employee" => [
                "id" => $lastPay->employee?->id,
                "employee_number" => $lastPay->employee?->employee_number,
                "full_name" => $lastPay->employee?->full_name,
                "tax_status" => $payrolls->first()?->tax_status,
            ],
            "last_pay" => [
                "id" => $lastPay->id,
                "last_pay_number" => $lastPay->last_pay_number,
                "year" => $lastPay->year,
                "stored_tax_refund" => round($this->numeric($lastPay->tax_refund), 2),
            ],
            "period" => [
                "start" => $periodStart,
                "end" => $periodEnd,
            ],
            "rows" => $rows,
            "totals" => [
                ...$totals,
                "annual_tax_due" => round($taxDue, 2),
                "computed_tax_refund" => round($computedRefund, 2),
                "difference_from_stored_tax_refund" => round($computedRefund - $this->numeric($lastPay->tax_refund), 2),
            ],
            "date_printed" => Carbon::now()->format("m/d/Y"),
        ];
    }

    public function buildLastPayPreview(LastPay $lastPay): array
    {
        $lastPay->loadMissing([
            "employee",
            "employee.current_branch",
            "employee.current_position",
            "deductions",
            "deductions.deduction",
        ]);

        $earnings = [
            "payroll_hold" => round($this->numeric($lastPay->payroll_hold), 2),
            "thirteenth_month" => round($this->numeric($lastPay->thirteenth_month), 2),
            "tax_refund" => round($this->numeric($lastPay->tax_refund), 2),
            "sales_incentives" => round($this->numeric($lastPay->sales), 2),
            "incentives" => round($this->numeric($lastPay->incentives), 2),
            "sl_vl" => round($this->numeric($lastPay->sl_vl), 2),
            "meal_allowance" => round($this->numeric($lastPay->meal), 2),
        ];

        $grossPay = round(array_sum($earnings), 2);
        $deductions = $lastPay->deductions->map(function ($deduction) {
            return [
                "id" => $deduction->id,
                "reference_doc" => $deduction->reference_doc,
                "deduction_id" => $deduction->deduction_id,
                "type" => $deduction->deduction?->description,
                "category" => $deduction->deduction?->category,
                "amount" => round($this->numeric($deduction->amount), 2),
            ];
        })->values();

        $governmentDeductions = $deductions
            ->filter(fn ($deduction) => in_array($deduction["category"], ["Pag-ibig", "SSS"]))
            ->values();
        $companyDeductions = $deductions
            ->reject(fn ($deduction) => in_array($deduction["category"], ["Pag-ibig", "SSS"])
                && $deduction["type"] !== "SSS Loans")
            ->values();
        $totalDeductions = round($deductions->sum("amount"), 2);
        $computedLastPay = round($grossPay - $totalDeductions, 2);

        return [
            "last_pay" => [
                "id" => $lastPay->id,
                "last_pay_number" => $lastPay->last_pay_number,
                "status" => $lastPay->status,
                "year" => $lastPay->year,
                "created_at" => $lastPay->created_at,
                "updated_at" => $lastPay->updated_at,
                "remarks" => $lastPay->remarks,
            ],
            "employee" => [
                "id" => $lastPay->employee?->id,
                "employee_number" => $lastPay->employee?->employee_number,
                "full_name" => $lastPay->employee?->full_name,
                "branch" => $lastPay->employee?->current_branch?->short_name,
                "branch_name" => $lastPay->employee?->current_branch?->name,
                "position" => $lastPay->employee?->current_position?->description,
            ],
            "earnings" => $earnings,
            "deductions" => [
                "company" => $companyDeductions,
                "government" => $governmentDeductions,
                "all" => $deductions,
            ],
            "totals" => [
                "gross_pay" => $grossPay,
                "total_deductions" => $totalDeductions,
                "computed_last_pay" => $computedLastPay,
                "stored_total_deductions" => round($this->numeric($lastPay->total_deductions), 2),
                "stored_last_pay" => round($this->numeric($lastPay->last_pay), 2),
                "difference_from_stored_total_deductions" => round($totalDeductions - $this->numeric($lastPay->total_deductions), 2),
                "difference_from_stored_last_pay" => round($computedLastPay - $this->numeric($lastPay->last_pay), 2),
            ],
            "formula" => "payroll_hold + thirteenth_month + tax_refund + sales_incentives + incentives + sl_vl + meal_allowance - total_deductions",
        ];
    }

    function numeric($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    function getTaxRefundPeriod(int $year): array
    {
        return [
            "start" => Carbon::create($year - 1, 12, 26)->format("Y-m-d"),
            "end" => Carbon::create($year, 12, 25)->format("Y-m-d"),
        ];
    }

    function getCutOffForDate($date){
        $carbonDate = Carbon::parse($date);
        $day = $carbonDate->day;

        $cutOff1 = [11,12,13,14,15,16,17,18,19,20,21,22,23,24,25];
        $cutOff2 = [26,27,28,29,30,31,1,2,3,4,5,6,7,8,9,10];
        if (in_array($day, $cutOff2)) {
            if ($day <= 10) { 
                $startTime = $carbonDate->copy()->setDay(26)->subMonth()->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->endOfDay();
            } else {
                $startTime = $carbonDate->copy()->setDay(26)->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->addMonth()->endOfDay();
            }
        } else {
            $startTime = $carbonDate->copy()->setDay(11)->startOfDay();
            $endTime = $carbonDate->copy()->setDay(25)->endOfDay();
        }

        return [
            'start_time' => $startTime->toDateString(),
            'end_time' => $endTime->toDateString(),
        ];
    }
}
