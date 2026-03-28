<?php

namespace App\Http\Services;

use App\Jobs\ComputeEmployeeTardiness;
use App\Models\Adjustment;
use App\Models\AdjustmentTimeKeeping;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Incentives;
use App\Models\IncentivesBracket;
use App\Models\IncentivesManagement;
use App\Models\IncentivesPerEmployee;
use App\Models\Pagibig;
use App\Models\Payroll;
use App\Models\PayrollList;
use App\Models\PhilhealthPercentageBase;
use App\Models\Position;
use App\Models\SSS;
use App\Models\Tardiness;
use App\Models\Tax;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;
use Illuminate\Validation\Rules\Exists;

class PayrollService {
    public function computePayroll (
        TimeKeepingSummary $timeKeepingSummary,
        $edit = false
    ) {
        $payroll = null;
        try {
            DB::beginTransaction();

            $employee = $timeKeepingSummary->employee;
            // $employee->loadMissing(["payroll_list", "schedule.schedule_type"]);
            $scheduleName = $employee->schedule?->schedule_type?->name ?? "Normal Schedule";
            $payrollList = $employee->payroll_list;
            $salaryAmount = (float) ($payrollList?->salary_amount ?? 0);
            $useSalaryAmount = $salaryAmount > 0;

            if (!$payrollList || $payrollList->is_included == 0) {
                return $payroll;
            }

            $daily_rate = round($employee->dailySalary(), 2);
            if ($useSalaryAmount) {
                $divisor = $scheduleName === "Compressed Schedule" ? 22 : 26;
                $daily_rate = round($salaryAmount / $divisor, 2);
            }
            $hourly_rate = $scheduleName === "Compressed Schedule" 
                ? round($daily_rate / 9.5, 2)
                : round($daily_rate / 8, 2);
            $minute_rate = $hourly_rate / 60;

            $cutOff = Cutoff::where("cut_off", $timeKeepingSummary->cut_off)->first();
            $paymentType = $employee->current_position->timekeeping_type;
            $cutOffBaseSalary = $employee->cutOffBaseSalary();
            if ($useSalaryAmount) {
                $cutOffBaseSalary = round($salaryAmount / 2, 2);
            }
            $baseRegularDays = $scheduleName === "Compressed Schedule" ? 11 : 13;
            $salary = 0;
            $tardinessAmount = 0;
            $undertimeAmount = 0;
            $overtimeAmount = 0;
            $legalHolidayAmount = 0;
            $legalHolidayOvertimeAmount = 0;
            $specialHolidayAmount = 0;
            $specialHolidayOvertimeAmount = 0;
            $nightDifferentialAmount = 0;
            $nightDifferentialOvertimeAmount = 0;
            $cancelledOffAmount = 0;
            $nonTaxableAmount = 0;
            $taxableAdustmentAmount = $timeKeepingSummary->adjustment_amount ?? 0;
            $philHealthAmount = 0;
            $pagibigEmployee = 0;
            $pagibigEmployer = 0;
            $sssEmployeeAmount = 0;
            $sssEmployerAmount = 0;
            $taxAmount = 0;
            $grossPayAmount = 0;
            $netPayAmount = 0;
            $totalDeductions = 0;
            $manualDeductions = 0;
            $remarks = null;

            $contributionTotal = 0;
            $additionalWorkingHoursTotal = 0;
            $workingHoursDeductionTotal = 0;

            if ($paymentType === "Monthly") {
                if ($timeKeepingSummary->regular_days < $baseRegularDays) {
                    $salary = $daily_rate * $timeKeepingSummary->regular_days;
                } else {
                    $salary = $cutOffBaseSalary;
                }
            } else {
                $salary = $daily_rate * $timeKeepingSummary->regular_days;
            }

            $tardinessAmount = $timeKeepingSummary->tardiness * $minute_rate;
            $undertimeAmount = $timeKeepingSummary->undertime * $minute_rate;
            $overtimeAmount = $timeKeepingSummary->regular_overtime * ($hourly_rate * 1.25);
            $legalHolidayAmount = $timeKeepingSummary->legal_holiday * $hourly_rate;
            $legalHolidayOvertimeAmount = $timeKeepingSummary->legal_holiday_overtime * ($hourly_rate * 2.6);
            $specialHolidayAmount = $timeKeepingSummary->special_holiday * ($hourly_rate * .3);
            $specialHolidayOvertimeAmount = $timeKeepingSummary->special_holiday_overtime * ($hourly_rate * 1.69);
            $nightDifferentialAmount = $timeKeepingSummary->night_differential * ($hourly_rate * .1);
            $nightDifferentialOvertimeAmount = $timeKeepingSummary->night_differential_overtime * ($hourly_rate * .125);
            $cancelledOffAmount = $timeKeepingSummary->cancelled_off * ($hourly_rate * 1.3);
            $basicSalary = $salary - ($tardinessAmount + $undertimeAmount);

            $deductions = [];
            $totalDeductions = 0;
        
            $existingPayroll = Payroll::where("employee_id", $employee->id)
            ->where("cut_off", $cutOff->cut_off)
            ->where("start_date", $cutOff->start_date)
            ->where("end_date", $cutOff->end_date)
            ->first();

            if (!$existingPayroll) {
                $employeeDeductions = EmployeeDeduction::with(["deduction"])
                ->where("employee_id", $employee->id)
                ->where("balance", ">", 0.01)
                ->where("date_created", "<=", $cutOff->pay_date)
                ->where("end_loan_status", '!=', 'Yes')
                ->where("approved_status", "Yes")
                ->get();
    
                foreach($employeeDeductions as $deduction) {
                    $amountToDeduct = $deduction->monthly_amortization;

                    if ($deduction->months > 0) {
                        $amountToDeduct = $deduction->monthly_amortization / 2;
                    }

                    if ($amountToDeduct > $deduction->balance) {
                        $amountToDeduct = $deduction->balance;
                    }
    
                    $deductions[] = [
                        "deduction_id" => $deduction->id,
                        "type" => $deduction->deduction->description,
                        "monthly_amortization" => $deduction->monthly_amortization,
                        "balance" => $deduction->balance,
                        "deducted_amount" => $amountToDeduct,
                        "payslip_description" => $deduction->deduction->payslip_description
                    ];
                    $totalDeductions += $amountToDeduct;
                }
            } else {
                $totalDeductions = 0;
                $deductions = json_decode($existingPayroll->deductions, true);

                foreach ($deductions as $ded) {
                    $totalDeductions += $ded["deducted_amount"];
                }

                $manualDeductions = $existingPayroll->manual_deductions;
                $nonTaxableAmount = $existingPayroll->non_taxable_adjustment;
                $remarks = $existingPayroll->remarks;
                if (!$edit) {
                    $taxableAdustmentAmount = $existingPayroll->taxable_adjustment;
                }
            }

            $grossPayAmount = ( $salary +
                $overtimeAmount +
                $legalHolidayAmount +
                $legalHolidayOvertimeAmount + 
                $specialHolidayAmount +
                $specialHolidayOvertimeAmount +
                $nightDifferentialAmount +
                $nightDifferentialOvertimeAmount +
                $cancelledOffAmount +
                $taxableAdustmentAmount) -
                $undertimeAmount -
                $tardinessAmount;

            $lastCutOffStartDate = Carbon::parse($cutOff->start_date)
                ->subMonths(1)
                ->day(26)
                ->format("Y-m-d");

            $latestPayroll = Payroll::where("employee_id", $employee->id)
                ->where("start_date", $lastCutOffStartDate)
                ->first();

            //clarify if $grossPayAmount will be used
            if ($payrollList->has_complete_documents == 1) {
                $philHealth = PhilhealthPercentageBase::first();

                $philHealthAmount = round(($basicSalary * ($philHealth->percentage * 0.01)) / 2 , 2);
                
                if ($basicSalary <= $philHealth->philhealth_cap) {
                    $philHealthAmount = round(($philHealth->philhealth_cap * ($philHealth->percentage * 0.01)) / 2 , 2);
                }

                if ($cutOff->part == 2) {
                    if ($latestPayroll) {
                        $latestBasic = $latestPayroll->salary - ($latestPayroll->undertime_amount + $latestPayroll->tardiness_amount);
                        $totalSalary = $basicSalary + $latestBasic;
                        $amount = round(($totalSalary * ($philHealth->percentage * 0.01)) , 2);

                        if ($amount > $philHealth->philhealth_max) {
                            $amount = $philHealth->philhealth_max;
                        }

                        $philHealthAmount = round(($amount - ($latestPayroll->employee_philhealth + $latestPayroll->employee_philhealth)) / 2, 2);
                    }
                }

                if ($philHealthAmount * 2 > $philHealth->philhealth_max) {
                    $philHealthAmount = $philHealth->philhealth_max / 2;
                }

                $sss = SSS::where('salary_range_min', '<=', $grossPayAmount)
                ->where('salary_range_max', '>=', $grossPayAmount)
                ->first();
    
                $pagibig = Pagibig::where('min_amount', '<=', $basicSalary)
                    ->where('max_amount', '>=', $basicSalary)
                    ->first();
    
                if ($cutOff->part == 1) {
                    $sssEmployeeAmount = round($sss->employee_ss, 2);
                    $sssEmployerAmount = round($sss->employer_ss + $sss->employer_cc, 2);   
    
                    $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                    $pagibigAmount = $pagibigShares > 49.99 ? $pagibig->max_deduction / 2: $pagibigShares;

                    $pagibigEmployee = round($pagibigAmount, 2);
                    $pagibigEmployer = round($pagibigAmount, 2);
                } else {    
                    if ($latestPayroll) {
                        $totalSalary = $grossPayAmount + $latestPayroll->gross_pay;
                        $sss = SSS::where('salary_range_min', '<=', $totalSalary)
                        ->where('salary_range_max', '>=', $totalSalary)
                        ->first();

                        $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                        $pagibigAmount = $pagibigShares > 49.99 ? $pagibig->max_deduction / 2: $pagibigShares;

                        $pagibigEmployee = round($pagibigAmount, 2);
                        $pagibigEmployer = round($pagibigAmount, 2);

                        $latestSSS = SSS::where('salary_range_min', '<=', $latestPayroll->gross_pay)
                        ->where('salary_range_max', '>=', $latestPayroll->gross_pay)
                        ->first();

                        if ($latestSSS) {    
                            $erCC = $sss->employer_cc - $latestSSS->employer_cc ?? 0;
                            $sssEmployeeAmount = round($sss->employee_ss - $latestPayroll->employee_sss, 2);
                            $sssEmployerAmount = round(($sss->employer_ss + $erCC) - $latestPayroll->employer_sss, 2);
                        }
                    } else {
                        $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                        $pagibigAmount = $pagibigShares > 49.99 ? $pagibig->max_deduction / 2: $pagibigShares;

                        $pagibigEmployee = round($pagibigAmount, 2);
                        $pagibigEmployer = round($pagibigAmount, 2);
    
                        $sssEmployeeAmount = round($sss->employee_ss, 2);
                        $sssEmployerAmount = round($sss->employer_ss + $sss->employer_cc, 2);
                    }
                }
            }

            $taxableIncome = round($grossPayAmount - $philHealthAmount - $pagibigEmployee - $sssEmployeeAmount, 2);
            $taxable = $taxableIncome;

            if ($this->isNcrMinimum($employee)) {
                $taxAmount = 0;
            } else {
                $tax = Tax::where("range_min", '<=' ,round($taxable, 2))
                ->where("range_max", '>=',round($taxable, 2))
                ->first();

                $salaryBase = $tax->salary_base ?? 0;
                $exemption = $tax->exemption ?? 0;
                $percentage = $tax->percentage ?? 0;

                $taxAmount = (($taxable - $salaryBase) * ($percentage* 0.01)) + $exemption > 0
                    ? round( (($taxable - $salaryBase) * ($percentage * 0.01)) + $exemption, 2)
                    : 0;
            }

            $contributionTotal = $sssEmployeeAmount + $pagibigEmployee + $philHealthAmount;
            $additionalWorkingHoursTotal = $overtimeAmount + $legalHolidayAmount + $legalHolidayOvertimeAmount + $specialHolidayAmount + $specialHolidayOvertimeAmount + $nightDifferentialAmount + $nightDifferentialOvertimeAmount + $cancelledOffAmount;
            $workingHoursDeductionTotal = $undertimeAmount + $tardinessAmount;
            $netPayAmount = $taxable - $taxAmount;

            $removedDeductions = [];
            $newDeductions = [];

            $netPayCheck = $netPayAmount - $totalDeductions - $manualDeductions + $nonTaxableAmount;
            $companyLoans = collect($deductions)->filter(fn($deduct) => !in_array($deduct['type'], ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]))
            ->sortBy('deducted_amount');
            $governmentLoans = collect($deductions)->filter(fn($deduct) => in_array($deduct['type'], ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]))
            ->sortBy('deducted_amount');
            $netPayNoDeductions = $netPayAmount + $nonTaxableAmount;
            $companyLoans = $companyLoans->values()->toArray();
            $governmentLoans = $governmentLoans->values()->toArray();

            if ($netPayCheck < 0) {
                foreach ($companyLoans as $deduction) {
                    $netPayNoDeductions -= $deduction['deducted_amount'];

                    if ($netPayNoDeductions < 0) {
                        $totalDeductions -= $deduction['deducted_amount'];
                        $netPayNoDeductions += $deduction['deducted_amount'];
                        $removedDeductions[] = $deduction['deduction_id'];
                    }
                }

                foreach ($governmentLoans as $deduction) {
                    $netPayNoDeductions -= $deduction['deducted_amount'];

                    if ($netPayNoDeductions < 0) {
                        $totalDeductions -= $deduction['deducted_amount'];
                        $netPayNoDeductions += $deduction['deducted_amount'];
                        $removedDeductions[] = $deduction['deduction_id'];
                    }
                }
            }

            foreach ($deductions as $deduction) {
                if (!in_array($deduction["deduction_id"], $removedDeductions)) {
                    $newDeductions[] = $deduction;
                }
            }

            $netPayAmount = $netPayAmount - $totalDeductions - $manualDeductions + $nonTaxableAmount;

            $payroll = Payroll::updateOrCreate([
                "employee_id" => $employee->id,
                "cut_off" => $cutOff->cut_off,
                "start_date" => $cutOff->start_date,
                "end_date" => $cutOff->end_date,
            ], [
                "timekeeping_summary_id" => $timeKeepingSummary->id,
                "timekeeping_branch_id" => $timeKeepingSummary->branch_id,
                "pay_date" => $cutOff->pay_date,
                "daily_rate" => $daily_rate,
                "hourly_rate" => $hourly_rate,
                "pay_type" => $paymentType,
                "tax_status" => $employee->tax_type,
                "salary" => round($salary,2),
                "tardiness_amount" => round($tardinessAmount,2),
                "undertime_amount" => round($undertimeAmount,2),
                "overtime_amount" => round($overtimeAmount,2),
                "legal_holiday_amount" => round($legalHolidayAmount,2),
                "legal_holiday_overtime_amount" => round($legalHolidayOvertimeAmount,2),
                "special_holiday_amount" => round($specialHolidayAmount,2),
                "special_holiday_overtime_amount" => round($specialHolidayOvertimeAmount,2),
                "night_differential_amount" => round($nightDifferentialAmount,2),
                "night_differential_overtime_amount" => round($nightDifferentialOvertimeAmount,2),
                "taxable_adjustment" => round($taxableAdustmentAmount,2),
                "non_taxable_adjustment" => round($nonTaxableAmount,2),
                "gross_pay" => round($grossPayAmount,2),
                "employee_sss" => $sssEmployeeAmount,
                "employer_sss" => $sssEmployerAmount,
                "employee_pagibig" => $pagibigEmployee,
                "employer_pagibig" => $pagibigEmployer,
                "employee_philhealth" => $philHealthAmount,
                "employer_philhealth" => $philHealthAmount,
                "withholding_tax" => round($taxAmount,2),
                "deductions" => json_encode($newDeductions),
                "total_deductions" => round($totalDeductions,2),
                "manual_deductions" => $manualDeductions,
                "cancelled_off" => round($cancelledOffAmount,2),
                "net_pay" => round($netPayAmount, 2),
                "taxable_income" => round($taxableIncome, 2),
                "contribution_total" => round($contributionTotal,2),
                "additional_working_hours_total" => round($additionalWorkingHoursTotal, 2),
                "working_hours_deduction_total" => round($workingHoursDeductionTotal, 2),
                "status" => "initial",
                "is_maternity" => $payrollList->is_on_maternity_leave,
                "is_hold" => $payrollList->is_hold,
                "remarks" => $remarks,
                "position_id" => $employee->position_id
            ]);

            DB::commit();
            return [
                "error" => false,
                "payroll" => $payroll
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("ERROR COMPUTING PAYROLL TIMEKEEPING: " .$timeKeepingSummary->id." EMPLOYEE: " .$employee->full_name. " ERROR: ". $e->getMessage());
            
            return [
                "error" => true,
                "payroll" => $payroll
            ];
        }
    }

    public function computePayrollV2($payrollDetails, $cutOff, $edit = false) {
        $payroll = null;
        $employee = $payrollDetails->employee;
        try {
            $daily_rate = $payrollDetails->daily_rate;
            $hourly_rate = $payrollDetails->hourly_rate;
            $minute_rate = $payrollDetails->minute_rate;
            $salary = 0;
            $tardinessAmount = 0;
            $undertimeAmount = 0;
            $overtimeAmount = 0;
            $legalHolidayAmount = 0;
            $legalHolidayOvertimeAmount = 0;
            $specialHolidayAmount = 0;
            $specialHolidayOvertimeAmount = 0;
            $nightDifferentialAmount = 0;
            $nightDifferentialOvertimeAmount = 0;
            $cancelledOffAmount = 0;
            $nonTaxableAmount = $payrollDetails->non_taxable_adjustment ?? 0;
            $taxableAdustmentAmount = $payrollDetails->adjustment_amount ?? 0;
            $philHealthAmount = 0;
            $pagibigEmployee = 0;
            $pagibigEmployer = 0;
            $sssEmployeeAmount = 0;
            $sssEmployerAmount = 0;
            $taxAmount = 0;
            $grossPayAmount = 0;
            $netPayAmount = 0;
            $totalDeductions = 0;
            $manualDeductions = 0;
            $remarks = null;
            $specialLeave = 0;

            $contributionTotal = 0;
            $additionalWorkingHoursTotal = 0;
            $workingHoursDeductionTotal = 0;

            $specialLeave = $daily_rate * $payrollDetails->special_leave;
            $salary = ($daily_rate * $payrollDetails->regular_days) + $specialLeave;

            $tardinessAmount = $payrollDetails->tardiness * $minute_rate;
            $undertimeAmount = $payrollDetails->undertime * $minute_rate;
            $overtimeAmount = $payrollDetails->regular_overtime * ($hourly_rate * 1.25);
            $legalHolidayAmount = $payrollDetails->legal_holiday * $hourly_rate;
            $legalHolidayOvertimeAmount = $payrollDetails->legal_holiday_overtime * ($hourly_rate * 2.6);
            $specialHolidayAmount = $payrollDetails->special_holiday * ($hourly_rate * .3);
            $specialHolidayOvertimeAmount = $payrollDetails->special_holiday_overtime * ($hourly_rate * 1.69);
            $nightDifferentialAmount = $payrollDetails->night_differential * ($hourly_rate * .1);
            $nightDifferentialOvertimeAmount = $payrollDetails->night_differential_overtime * ($hourly_rate * .125);
            $cancelledOffAmount = $payrollDetails->cancelled_off * ($hourly_rate * 1.3);
            $basicSalary = $salary - ($tardinessAmount + $undertimeAmount);

            $deductions = [];
            $totalDeductions = 0;

            $existingPayroll = Payroll::where("employee_id", $employee->id)
            ->where("cut_off", $cutOff->cut_off)
            ->where("start_date", $cutOff->start_date)
            ->where("end_date", $cutOff->end_date)
            ->first();

            if (
                $payrollDetails->is_mancom == 0 &&
                $payrollDetails->is_on_maternity_leave == 0
            ) {
                if (!$existingPayroll || $existingPayroll->net_pay == 0) {
                    $employeeDeductions = EmployeeDeduction::with(["deduction"])
                    ->where("employee_id", $employee->id)
                    ->where("balance", ">", 0.01)
                    ->where("date_created", "<=", $cutOff->pay_date)
                    ->where("end_loan_status", '!=', 'Yes')
                    ->where("approved_status", "Yes")
                    ->get();
        
                    foreach($employeeDeductions as $deduction) {
                        $amountToDeduct = $deduction->monthly_amortization;
    
                        if ($deduction->months > 0) {
                            $amountToDeduct = $deduction->monthly_amortization / 2;
                        }
    
                        if ($amountToDeduct > $deduction->balance) {
                            $amountToDeduct = $deduction->balance;
                        }
        
                        $deductions[] = [
                            "deduction_id" => $deduction->id,
                            "type" => $deduction->deduction->description,
                            "monthly_amortization" => $deduction->monthly_amortization,
                            "balance" => $deduction->balance,
                            "deducted_amount" => $amountToDeduct,
                            "payslip_description" => $deduction->deduction->payslip_description
                        ];
                        $totalDeductions += $amountToDeduct;
                    }
                } else {
                    $totalDeductions = 0;
                    $deductions = json_decode($existingPayroll->deductions, true);
    
                    foreach ($deductions as $ded) {
                        $totalDeductions += $ded["deducted_amount"];
                    }
                }
            }

            $grossPayAmount = ( $salary +
                $overtimeAmount +
                $legalHolidayAmount +
                $legalHolidayOvertimeAmount + 
                $specialHolidayAmount +
                $specialHolidayOvertimeAmount +
                $nightDifferentialAmount +
                $nightDifferentialOvertimeAmount +
                $cancelledOffAmount +
                $taxableAdustmentAmount) -
                $undertimeAmount -
                $tardinessAmount;

            $lastCutOffStartDate = Carbon::parse($cutOff->start_date)
                ->subMonths(1)
                ->day(26)
                ->format("Y-m-d");

            $latestPayroll = Payroll::where("employee_id", $employee->id)
                ->where("start_date", $lastCutOffStartDate)
                ->first();

            //clarify if $grossPayAmount will be used
            if ($payrollDetails->is_complete_documents == 1) {
                $philHealth = PhilhealthPercentageBase::first();

                $philHealthAmount = round(($basicSalary * ($philHealth->percentage * 0.01)) / 2 , 2);
                
                if ($basicSalary <= $philHealth->philhealth_cap) {
                    $philHealthAmount = round(($philHealth->philhealth_cap * ($philHealth->percentage * 0.01)) / 2 , 2);
                }

                if ($cutOff->part == 2) {
                    if ($latestPayroll) {
                        $latestBasic = $latestPayroll->salary - ($latestPayroll->undertime_amount + $latestPayroll->tardiness_amount);
                        $totalSalary = $basicSalary + $latestBasic;
                        $amount = round(($totalSalary * ($philHealth->percentage * 0.01)) , 2);

                        if ($amount > $philHealth->philhealth_max) {
                            $amount = $philHealth->philhealth_max;
                        }

                        $totalPhilhealthAmount = round(($amount - ($latestPayroll->employee_philhealth + $latestPayroll->employer_philhealth)), 2);
                        $philHealthAmount = round(($totalPhilhealthAmount / 2), 2);

                        if ($philHealthAmount < 0) {
                            $philHealthAmount = 0;
                        }
                    }
                }

                if ($philHealthAmount * 2 > $philHealth->philhealth_max) {
                    $philHealthAmount = $philHealth->philhealth_max / 2;
                }

                $sss = SSS::where('salary_range_min', '<=', $grossPayAmount)
                ->where('salary_range_max', '>=', $grossPayAmount)
                ->first();
    
                $pagibig = Pagibig::where('min_amount', '<=', $basicSalary)
                    ->where('max_amount', '>=', $basicSalary)
                    ->first();
    
                if ($cutOff->part == 1) {
                    $sssEmployeeAmount = round($sss->employee_ss, 2);
                    $sssEmployerAmount = round($sss->employer_ss + $sss->employer_cc, 2);   
    
                    $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                    $pagibigAmount = $pagibigShares > 49.99 ? $pagibig->max_deduction / 2: $pagibigShares;

                    $pagibigEmployee = round($pagibigAmount, 2);
                    $pagibigEmployer = round($pagibigAmount, 2);
                } else {    
                    if ($latestPayroll) {
                        $latestBasic = $latestPayroll->salary - ($latestPayroll->undertime_amount + $latestPayroll->tardiness_amount);
                        $totalBasic = $basicSalary + $latestBasic;

                        $totalSalary = $grossPayAmount + $latestPayroll->gross_pay;
                        $sss = SSS::where('salary_range_min', '<=', $totalSalary)
                        ->where('salary_range_max', '>=', $totalSalary)
                        ->first();

                        $pagibigShares = $totalBasic * (0.01 * $pagibig->percentage);
                        $pagibigAmount = $pagibigShares > 49.99
                            ? $pagibig->max_deduction - $latestPayroll->employee_pagibig
                            : $pagibigShares - $latestPayroll->employee_pagibig;

                        $pagibigEmployee = round($pagibigAmount, 2);
                        $pagibigEmployer = round($pagibigAmount, 2);

                        $latestSSS = SSS::where('salary_range_min', '<=', $latestPayroll->gross_pay)
                        ->where('salary_range_max', '>=', $latestPayroll->gross_pay)
                        ->first();

                        if ($latestSSS) {
                            $sssEmployeeAmount = round($sss->employee_ss - $latestPayroll->employee_sss, 2);
                            $sssEmployerAmount = round(($sss->employer_ss + $sss->employer_cc) - $latestPayroll->employer_sss, 2);
                        } else {
                            $sssEmployeeAmount = round($sss->employee_ss, 2);
                            $sssEmployerAmount = round($sss->employer_ss + $sss->employer_cc, 2);
                        }
                    } else {
                        $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                        $pagibigAmount = $pagibigShares > 49.99 ? $pagibig->max_deduction / 2: $pagibigShares;

                        $pagibigEmployee = round($pagibigAmount, 2);
                        $pagibigEmployer = round($pagibigAmount, 2);
    
                        $sssEmployeeAmount = round($sss->employee_ss, 2);
                        $sssEmployerAmount = round($sss->employer_ss + $sss->employer_cc, 2);
                    }
                }
            }

            $taxableIncome = round($grossPayAmount - $philHealthAmount - $pagibigEmployee - $sssEmployeeAmount, 2);
            $taxable = $taxableIncome;

            if ($this->isNcrMinimum($employee) || $payrollDetails->is_on_maternity_leave == 1) {
                $taxAmount = 0;
            } else {
                $tax = Tax::where("range_min", '<=' ,round($taxable, 2))
                ->where("range_max", '>=',round($taxable, 2))
                ->first();

                $salaryBase = $tax->salary_base ?? 0;
                $exemption = $tax->exemption ?? 0;
                $percentage = $tax->percentage ?? 0;

                $taxAmount = (($taxable - $salaryBase) * ($percentage* 0.01)) + $exemption > 0
                    ? round( (($taxable - $salaryBase) * ($percentage * 0.01)) + $exemption, 2)
                    : 0;
            }

            $contributionTotal = $sssEmployeeAmount + $pagibigEmployee + $philHealthAmount;
            $additionalWorkingHoursTotal = $overtimeAmount + $legalHolidayAmount + $legalHolidayOvertimeAmount + $specialHolidayAmount + $specialHolidayOvertimeAmount + $nightDifferentialAmount + $nightDifferentialOvertimeAmount + $cancelledOffAmount;
            $workingHoursDeductionTotal = $undertimeAmount + $tardinessAmount;
            $netPayAmount = $taxable - $taxAmount;

            $removedDeductions = [];
            $newDeductions = [];

            $netPayCheck = $netPayAmount - $totalDeductions - $manualDeductions + $nonTaxableAmount;
            $companyLoans = collect($deductions)->filter(fn($deduct) => !in_array($deduct['type'], ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]))
            ->sortBy('deducted_amount');
            $governmentLoans = collect($deductions)->filter(fn($deduct) => in_array($deduct['type'], ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]))
            ->sortBy('deducted_amount');
            $netPayNoDeductions = $netPayAmount + $nonTaxableAmount;
            $companyLoans = $companyLoans->values()->toArray();
            $governmentLoans = $governmentLoans->values()->toArray();

            if ($netPayCheck < 0) {
                foreach ($companyLoans as $deduction) {
                    $netPayNoDeductions -= $deduction['deducted_amount'];

                    if ($netPayNoDeductions < 0) {
                        $totalDeductions -= $deduction['deducted_amount'];
                        $netPayNoDeductions += $deduction['deducted_amount'];
                        $removedDeductions[] = $deduction['deduction_id'];
                    }
                }

                foreach ($governmentLoans as $deduction) {
                    $netPayNoDeductions -= $deduction['deducted_amount'];

                    if ($netPayNoDeductions < 0) {
                        $totalDeductions -= $deduction['deducted_amount'];
                        $netPayNoDeductions += $deduction['deducted_amount'];
                        $removedDeductions[] = $deduction['deduction_id'];
                    }
                }
            }

            foreach ($deductions as $deduction) {
                if (!in_array($deduction["deduction_id"], $removedDeductions)) {
                    $newDeductions[] = $deduction;
                }
            }

            $netPayAmount = $netPayAmount - $totalDeductions - $manualDeductions + $nonTaxableAmount;

            $payroll = [
                "salary" => round($salary,2),
                "tardiness_amount" => round($tardinessAmount,2),
                "undertime_amount" => round($undertimeAmount,2),
                "overtime_amount" => round($overtimeAmount,2),
                "legal_holiday_amount" => round($legalHolidayAmount,2),
                "legal_holiday_overtime_amount" => round($legalHolidayOvertimeAmount,2),
                "special_holiday_amount" => round($specialHolidayAmount,2),
                "special_holiday_overtime_amount" => round($specialHolidayOvertimeAmount,2),
                "night_differential_amount" => round($nightDifferentialAmount,2),
                "night_differential_overtime_amount" => round($nightDifferentialOvertimeAmount,2),
                "taxable_adjustment" => round($taxableAdustmentAmount,2),
                "non_taxable_adjustment" => round($nonTaxableAmount,2),
                "gross_pay" => round($grossPayAmount,2),
                "employee_sss" => $sssEmployeeAmount,
                "employer_sss" => $sssEmployerAmount,
                "employee_pagibig" => $pagibigEmployee,
                "employer_pagibig" => $pagibigEmployer,
                "employee_philhealth" => $philHealthAmount,
                "employer_philhealth" => $philHealthAmount,
                "withholding_tax" => round($taxAmount,2),
                "deductions" => json_encode($newDeductions),
                "total_deductions" => round($totalDeductions,2),
                "manual_deductions" => $manualDeductions,
                "cancelled_off" => round($cancelledOffAmount,2),
                "net_pay" => round($netPayAmount, 2),
                "taxable_income" => round($taxableIncome, 2),
                "contribution_total" => round($contributionTotal,2),
                "additional_working_hours_total" => round($additionalWorkingHoursTotal, 2),
                "working_hours_deduction_total" => round($workingHoursDeductionTotal, 2),
                "status" => "initial",
                "is_maternity" => $payrollDetails->is_on_maternity_leave,
                "is_hold" => $payrollDetails->is_hold,
                "remarks" => $remarks,
                "position_id" => $employee->position_id,
                "special_leave" => $specialLeave
            ];

            return (object) $payroll;
        } catch (Exception $e) {
            Log::error("ERROR COMPUTING PAYROLL EMPLOYEE: " .$employee->full_name. " ERROR: ". $e->getMessage());
            return $payroll;
        }
    }

    public function computeIncentives(Cutoff $cutOff, Employee $employee, Branch $branch) {
        $incentivesAmount = 0;

        $position = $employee->current_position;
        $formerIncentives = Incentives::where("employee_id", $employee->id)
        ->whereNot("pay_date", $cutOff->pay_date)
        ->orderBy("pay_date", "DESC")
        ->first();

        if ($formerIncentives && $cutOff->part == 2) {
            $position = Position::where("id", $formerIncentives->position_id)->first();
        }

        if ($position->rank == 1) {
            $months = Carbon::now()->diffInMonths($employee->date_hired);
            $bracket = IncentivesBracket::where("month", "<=", $months)
                ->whereHas('positions', function ($query) use ($employee) {
                    $query->where('positions.id', $employee->position_id);
                })
                ->orderByRaw('CAST(month AS UNSIGNED) DESC')
                ->first();

            if ($bracket) {
                $incentivesAmount = $bracket->amount;
            }
            
            $rankAndFileIncentive = IncentivesPerEmployee::where("employee_id", $employee->id)->first();

            if ($rankAndFileIncentive) {
                $incentivesAmount = $rankAndFileIncentive->max_amount;
            }
        } else {
            $managementIncentives = IncentivesManagement::where("employee_id", $employee->id)->first();

            if ($managementIncentives) {
                $incentivesAmount = $managementIncentives->max_amount;
            }
        }

        $employeeId = $employee->id;
        $positionId = $position->id;
        $branchId = $branch->id;
        $incentiveAmount = 0;
        $maxAmount = $incentivesAmount;
        $remarks = "";
        $payDate = $cutOff->pay_date;
        $amountRate = $incentivesAmount;
        $additionalIncentive = 0;
        $adjustmentDays = 0;
        $adjustmentPercent = 0;
        $additionalDays = [];
        $status = "initial";
    
        $startDate = Carbon::parse($cutOff->start_date)->subMonths(1)->day(26)->format("Y-m-d");
        $endDate = Carbon::parse($cutOff->start_date)->day(25)->format("Y-m-d");

        $firstCutOff = $this->getCutOffForDate($startDate);
        $secondCutOff = $this->getCutOffForDate($endDate);
        $currentCutOff = $this->getCutOffForDate($cutOff->pay_date);

        $month = Carbon::parse($secondCutOff['end_time'])->month;
        $year = Carbon::parse($secondCutOff['end_time'])->year;
        
        $firstTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
        ->where("cut_off", $firstCutOff["start_time"]."-".$firstCutOff["end_time"])
        ->first();

        $secondTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
        ->where("cut_off", $secondCutOff["start_time"]."-".$secondCutOff["end_time"])
        ->first();

        $firstCutOffDays = $firstTimeKeeping->regular_days ?? 0;
        $secondCutOffDays = $secondTimeKeeping->regular_days ?? 0;

        $days = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13; 
        
        $firstCutOffAdjustments = Adjustment::where("employee_id", $employee->id)
        ->whereBetween("created_at", [$firstCutOff['start_time']." 00:00:00", $firstCutOff['end_time']." 23:59:59"])
        ->where('days' , '>', 0)
        ->whereIn("status", ["Approved", "Adjusted"])
        ->get();

        $secondCutOffAdjustments = Adjustment::where("employee_id", $employee->id)
        ->whereBetween("created_at", [$secondCutOff['start_time']." 00:00:00", $secondCutOff['end_time']." 23:59:59"])
        ->where('days' , '>', 0)
        ->whereIn("status", ["Approved", "Adjusted"])
        ->get();

        $adjustmentDays = ($days > $firstCutOffDays ? $days - $firstCutOffDays : 0) +
            ($days > $secondCutOffDays ? $days - $secondCutOffDays : 0);

        if ($adjustmentDays) {
            $remarks = "ABSENTS: ".$adjustmentDays. " | ";
        }

        foreach ($firstCutOffAdjustments as $adjustment) {
            $additionalDays[] = [
                "date" => $adjustment->date,
                "days" => $adjustment->days
            ];

            $firstCutOffDays += $adjustment->days;
        }

        foreach ($secondCutOffAdjustments as $adjustment) {
            $additionalDays[] = [
                "date" => $adjustment->date,
                "days" => $adjustment->days
            ];

            $secondCutOffDays += $adjustment->days;
        }

        $monthWorkedDays = $firstCutOffDays + $secondCutOffDays;
        $monthShouldBeWorkDays = 26;
        $dailyIncentives = $incentivesAmount / 26;
        if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
            $dailyIncentives = $incentivesAmount / 22; 
            $monthShouldBeWorkDays = 22;
        }

        if ($employee->current_position->timekeeping_type == "Daily") {
            if ($monthWorkedDays > $monthShouldBeWorkDays) {
                $incentiveAmount = $amountRate;
            } else {
                $incentiveAmount = $dailyIncentives * $monthWorkedDays;
            }
        } else {
            $incentiveAmount = $dailyIncentives * $monthWorkedDays;
        }

        $tardinessDateBasis = Carbon::parse($currentCutOff['end_time'])->subMonths(2);

        $tardiness = Tardiness::with(["tardiness_sanction"])
        ->where("year", $tardinessDateBasis->year)
        ->where("month", $tardinessDateBasis->month)
        ->where("employee_id", $employee->id)
        ->where("status", "Approved")
        ->first();

        if ($tardiness) {
            $percentage = $tardiness->tardiness_sanction->incentives_deduction;
            $adjustmentPercent = $percentage;

            if ($percentage > 0) {
                $deduction = $maxAmount * ($percentage * 0.01);

                if ($deduction >= $incentiveAmount) {
                    $incentiveAmount = 0;
                } else {
                    $incentiveAmount -= $deduction;
                }

                $tardiness->deduction_status = 1;
                $tardiness->save();
            }
        }

        $firstCutoffUndertime = $firstTimeKeeping->undertime ?? 0;
        $secondCutoffUndertime = $secondTimeKeeping->undertime ?? 0;
        $totalUndertimeForMonth = $firstCutoffUndertime + $secondCutoffUndertime;

        if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
            $allowedUndertimePerMonth = 900; 
            $basisForDeduction = 240; 

            $excessiveUndertime = $totalUndertimeForMonth - $allowedUndertimePerMonth;

            if ($excessiveUndertime > 0) {
                $numberToDeduct = floor($excessiveUndertime / $basisForDeduction);

                if ($numberToDeduct > 0) {
                    $amountToBeDeduct = $numberToDeduct * 0.5;
                    $adjustmentDays += $amountToBeDeduct;

                    $deductionAmount = ($amountRate / $monthShouldBeWorkDays) * $amountToBeDeduct;

                    $incentiveAmount -= $deductionAmount;

                    if (!empty($remarks)) {
                        $remarks .= $totalUndertimeForMonth." UNDERTIME DEDUCTION - ".round($deductionAmount, 2);
                    } else {
                        $remarks = $totalUndertimeForMonth." UNDERTIME DEDUCTION - ".round($deductionAmount, 2);
                    }
                }
            }       
        } else {
            if ($totalUndertimeForMonth >= 240) {
                $basisForDeduction = 240;

                $numberToDeduct = floor($totalUndertimeForMonth / $basisForDeduction);

                if ($numberToDeduct > 0) {
                    $amountToBeDeduct = $numberToDeduct * 0.5;
                    $adjustmentDays += $amountToBeDeduct;

                    $deductionAmount = ($amountRate / $monthShouldBeWorkDays) * $amountToBeDeduct;

                    $incentiveAmount -= $deductionAmount;

                    if (!empty($remarks)) {
                        $remarks .= $totalUndertimeForMonth." UNDERTIME DEDUCTION - ".round($deductionAmount, 2);
                    } else {
                        $remarks = $totalUndertimeForMonth." UNDERTIME DEDUCTION - ".round($deductionAmount, 2);
                    }
                }
            }
        }

        $existingIncentives = Incentives::where("employee_id", $employee->id)
        ->where("pay_date", $payDate)
        ->first();

        if ($existingIncentives) {
            if ($existingIncentives->additional_incentive > 0) {
                $additionalIncentive = $existingIncentives->additional_incentive;
            }

            if ($existingIncentives->adjustment_percent > 0) {
                $adjustmentPercent = $existingIncentives->adjustment_percent;
            }
        }
        
        $incentive = Incentives::updateOrCreate([
            "employee_id" => $employeeId,
            "position_id" => $positionId,
            "pay_date" => $currentCutOff['end_time'],
            "month" => $month,
            "year" => $year
        ],[
            "branch_id" => $branchId,
            "incentive_amount" => 0 > $incentiveAmount ? 0 : $incentiveAmount,
            "max_amount" => $maxAmount,
            "remarks" => $remarks,
            "amount_rate" => $amountRate,
            "additional_incentive" => $additionalIncentive,
            "adjustment_days" => $adjustmentDays,
            "adjustment_percent" => $adjustmentPercent,
            "first_cut_off_worked_days" => $firstCutOffDays,
            "second_cut_off_worked_days" => $secondCutOffDays,
            "additional_days" => json_encode($additionalDays),
            "status" => $status
        ]);

        return $incentive;
    }

    private function isNcrMinimum(Employee $employee): bool
    {
        $rateCategory = strtoupper((string) ($employee->current_position?->salary?->rate_category ?? ''));
        if ($rateCategory !== 'MINIMUM') {
            return false;
        }

        $branchLocation = $employee->current_branch?->location ?? $employee->home_branch?->location ?? '';
        $branchLocation = strtoupper(trim((string) $branchLocation));
        if ($branchLocation === '') {
            return false;
        }

        return str_contains($branchLocation, 'NCR');
    }

    public function getCutOffForDate($date){
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
