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
            $daily_rate = round($employee->dailySalary(), 2);
            $hourly_rate = $employee->schedule->schedule_type->name === "Compressed Schedule" 
                ? round($daily_rate / 9.5, 2)
                : round($daily_rate / 8, 2);
            $minute_rate = $hourly_rate / 60;

            $cutOff = Cutoff::where("cut_off", $timeKeepingSummary->cut_off)->first();
            $paymentType = $employee->current_position->timekeeping_type;
            $cutOffBaseSalary = $employee->cutOffBaseSalary();
            $baseRegularDays = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13;
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

            $timeKeepings = TimeKeeping::with(["payment"])
            ->where("employee_id", $employee->id)
            ->whereBetween("date", [$cutOff->start_date, $cutOff->end_date])
            ->get();

            foreach($timeKeepings as $timeKeeping) {
                $payment = $timeKeeping->payment;

                $tardinessAmount += (float)$payment->tardiness_deduction;
                $undertimeAmount += (float)$payment->undertime_deduction;
                $overtimeAmount += (float)$payment->regular_overtime;
                $legalHolidayAmount += $timeKeepingSummary->legal_holiday > 0 ? (float)$payment->legal_holiday : 0;
                $legalHolidayOvertimeAmount += $timeKeepingSummary->legal_holiday_overtime > 0 ? (float)$payment->legal_holiday_overtime : 0;
                $specialHolidayAmount += $timeKeepingSummary->special_holiday > 0 ? (float)$payment->special_holiday : 0;
                $specialHolidayOvertimeAmount += $timeKeepingSummary->special_holiday_overtime > 0 ? (float)$payment->special_holiday_overtime : 0;
                $nightDifferentialAmount += (float)$payment->night_differential;
                $nightDifferentialOvertimeAmount += (float)$payment->night_differential_overtime;
                $cancelledOffAmount += (float)$payment->cancelled_off;
            }

            $basicSalary = $salary - ($tardinessAmount + $undertimeAmount);
        
            $grossPayAmount = ( $salary +
                $overtimeAmount +
                $legalHolidayAmount +
                $legalHolidayOvertimeAmount + 
                $specialHolidayAmount +
                $specialHolidayOvertimeAmount +
                $nightDifferentialAmount +
                $nightDifferentialOvertimeAmount +
                $cancelledOffAmount +
                $taxableAdustmentAmount +
                $nonTaxableAmount ) -
                $undertimeAmount -
                $tardinessAmount;

            //clarify if $grossPayAmount will be used
            if ($employee->hasCompleteStatutories()) {
                $philHealth = PhilhealthPercentageBase::first();

                $philHealthAmount = round(($basicSalary * ($philHealth->percentage * 0.01)) / 2 , 2);
                
                if ($basicSalary <= $philHealth->philhealth_cap) {
                    $philHealthAmount = round(($philHealth->philhealth_cap * ($philHealth->percentage * 0.01)) / 2 , 2);
                }

                if ($cutOff->part == 2) {
                    $latestPayroll = Payroll::where("employee_id", $employee->id)
                        ->whereNot("start_date", $cutOff->start_date)
                        ->orderBy("start_date", "DESC")
                        ->first();

                    if ($latestPayroll) {
                        $totalSalary = $basicSalary + $latestPayroll->gross_pay;
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
    
                $pagibig = Pagibig::where('min_amount', '<=', $grossPayAmount)
                    ->where('max_amount', '>=', $grossPayAmount)
                    ->first();
    
                if ($cutOff->part == 1) {
                    $sssEmployeeAmount = round($sss->employee_ss, 2);
                    $sssEmployerAmount = round($sss->employer_ss + ($sss->employer_cc / 2), 2);   
    
                    $pagibigShares = $grossPayAmount * (0.01 * $pagibig->percentage);
                    $pagibigAmount = $pagibigShares > $pagibig->max_deduction ? $pagibig->max_deduction : $pagibigShares;
                    $pagibigEmployee = round($pagibigAmount / 2, 2);
                    $pagibigEmployer = round($pagibigAmount / 2, 2);
                } else {
                    $latestPayroll = Payroll::where("employee_id", $employee->id)
                    ->whereNot("start_date", $cutOff->start_date)
                    ->orderBy("start_date", "DESC")
                    ->first();
    
                    if ($latestPayroll) {
                        $totalSalary = $basicSalary + $latestPayroll->gross_pay;
    
                        $sss = SSS::where('salary_range_min', '<=', $totalSalary)
                        ->where('salary_range_max', '>=', $totalSalary)
                        ->first();
                        
                        $pagibig = Pagibig::where('min_amount', '<=', $totalSalary)
                        ->where('max_amount', '>=', $totalSalary)
                        ->first();
    
                        $pagibigShares = $totalSalary * (0.01 * $pagibig->percentage);

                        if ($pagibigShares > $pagibig->max_deduction) {
                            $pagibigShares = $pagibig->max_deduction;
                        }
                        $latestSSS = SSS::where("employee_ss", $latestPayroll->employee_sss)->first();

                        $pagibigEmployee = round($pagibigShares - $latestPayroll->employee_pagibig, 2);
                        $pagibigEmployer = round($pagibigShares - $latestPayroll->employer_pagibig, 2);

                        $erCC = $sss->employer_cc - $latestSSS->employer_cc ?? 0;
                        $sssEmployeeAmount = round($sss->employee_ss - $latestPayroll->employee_sss, 2);
                        $sssEmployerAmount = round(($sss->employer_ss + $erCC) - $latestPayroll->employer_sss, 2);   
                    } else {
                        $pagibigShares = $grossPayAmount * (0.01 * $pagibig->percentage);
                        $pagibigAmount = $pagibigShares > $pagibig->max_deduction ? $pagibig->max_deduction : $pagibigShares;
                        $pagibigEmployee = round($pagibigAmount / 2, 2);
                        $pagibigEmployer = round($pagibigAmount / 2, 2);
    
                        $sssEmployeeAmount = round($sss->employee_ss, 2);
                        $sssEmployerAmount = round($sss->employer_ss + $sss->employer_cc, 2);
                    }

                }
            }     

            $deductions = [];
            $totalDeductions = 0;

            $taxableIncome = round($grossPayAmount - $philHealthAmount - $pagibigEmployee - $sssEmployeeAmount, 2);
            $taxable = $taxableIncome;

            $existingPayroll = Payroll::where("employee_id", $employee->id)
            ->where("cut_off", $cutOff->cut_off)
            ->where("start_date", $cutOff->start_date)
            ->where("end_date", $cutOff->end_date)
            ->first();

            if (!$existingPayroll) {
                $employeeDeductions = EmployeeDeduction::with(["deduction"])
                ->where("employee_id", $employee->id)
                ->where("balance", ">", 1)
                ->where("date_created", "<=", $cutOff->pay_date)
                ->where("end_loan_status", "!=", "Yes")
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
                        "payslip_description" => $deduction->payslip_description
                    ];
                    $totalDeductions += $amountToDeduct;
                }
            } else {
                $totalDeductions = $existingPayroll->total_deductions;
                $deductions = json_decode($existingPayroll->deductions, true);
                $manualDeductions = $existingPayroll->manual_deductions;
                $nonTaxableAmount = $existingPayroll->non_taxable_adjustment;
                $remarks = $existingPayroll->remarks;
                if (!$edit) {
                    $taxableAdustmentAmount = $existingPayroll->taxable_adjustment;

                    $grossPayAmount = ( $salary +
                        $overtimeAmount +
                        $legalHolidayAmount +
                        $legalHolidayOvertimeAmount + 
                        $specialHolidayAmount +
                        $specialHolidayOvertimeAmount +
                        $nightDifferentialAmount +
                        $nightDifferentialOvertimeAmount +
                        $cancelledOffAmount +
                        $taxableAdustmentAmount +
                        $nonTaxableAmount ) -
                        $undertimeAmount -
                        $tardinessAmount;

                    $taxableIncome = round($grossPayAmount - $philHealthAmount - $pagibigEmployee - $sssEmployeeAmount, 2);
                    $taxable = $taxableIncome;
                }


                // $salaryBase = $tax->salary_base ?? 0;
                // $exemption = $tax->exemption ?? 0;
                // $percentage = $tax->percentage ?? 0;

                // $taxAmount = (($taxable - $salaryBase) * ($percentage* 0.01)) + $exemption > 0
                //     ? round( (($taxable - $salaryBase) * ($percentage * 0.01)) + $exemption, 2)
                //     : 0;

                // $contributionTotal = $sssEmployeeAmount + $pagibigEmployee + $philHealthAmount;
                // $additionalWorkingHoursTotal = $overtimeAmount + $legalHolidayAmount + $legalHolidayOvertimeAmount + $specialHolidayAmount + $specialHolidayOvertimeAmount + $nightDifferentialAmount + $nightDifferentialOvertimeAmount + $cancelledOffAmount + $taxableAdustmentAmount;
                // $workingHoursDeductionTotal = $undertimeAmount + $tardinessAmount;
                // $netPayAmount = $taxable - $taxAmount;
            }

            $tax = Tax::where("range_min", '<=' ,round($taxable, 2))
            ->where("range_max", '>=',round($taxable, 2))
            ->first();

            $salaryBase = $tax->salary_base ?? 0;
            $exemption = $tax->exemption ?? 0;
            $percentage = $tax->percentage ?? 0;

            $taxAmount = (($taxable - $salaryBase) * ($percentage* 0.01)) + $exemption > 0
                ? round( (($taxable - $salaryBase) * ($percentage * 0.01)) + $exemption, 2)
                : 0;

            $contributionTotal = $sssEmployeeAmount + $pagibigEmployee + $philHealthAmount;
            $additionalWorkingHoursTotal = $overtimeAmount + $legalHolidayAmount + $legalHolidayOvertimeAmount + $specialHolidayAmount + $specialHolidayOvertimeAmount + $nightDifferentialAmount + $nightDifferentialOvertimeAmount + $cancelledOffAmount;
            $workingHoursDeductionTotal = $undertimeAmount + $tardinessAmount;
            $netPayAmount = $taxable - $taxAmount;

            $netPayAmount = $netPayAmount - $totalDeductions - $manualDeductions + $nonTaxableAmount;

            $removedDeductions = [];

            $newDeductions = [];

            if ($netPayAmount < 1000) {

                $employeeDeductions = EmployeeDeduction::with(["deduction"])
                    ->where("employee_id", $employee->id)
                    ->where("balance", ">", 1)
                    ->where("date_created", "<=", $cutOff->pay_date)
                    ->where("end_loan_status", "!=", "Yes")
                    ->get();

                $companyLoans = $employeeDeductions->filter(fn($deduct) => !in_array($deduct->deduction->description, ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]));

                $governmentLoans = $employeeDeductions->filter(fn($deduct) => in_array($deduct->deduction->description, ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]));

                $companyLoans = $companyLoans->sortBy('monthly_amortization');
                $governmentLoans = $governmentLoans->sortBy('monthly_amortization');

                foreach ($companyLoans as $deduction) {
                    $amountToDeduct = $deduction->monthly_amortization / 2;
                    if ($amountToDeduct > $deduction->balance) {
                        $amountToDeduct = $deduction->balance;
                    }

                    $computedNetPay = $netPayAmount + $amountToDeduct;

                    if ($computedNetPay >= 1000) {
                        $totalDeductions -= $amountToDeduct;
                        $netPayAmount = $computedNetPay;

                        $removedDeductions[] = $deduction->deduction->id;

                        // $deduction->delete();
                        break;
                    } else {
                        $totalDeductions -= $amountToDeduct;
                        $netPayAmount += $amountToDeduct;
    
                        $removedDeductions[] = $deduction->deduction->id;
    
                        // $deduction->delete();
                    }
                }

                if ($netPayAmount < 1000) {
                    $amountToDeduct = 0;
                    foreach ($governmentLoans as $deduction) {
                        $amountToDeduct = $deduction->monthly_amortization;
                        if ($deduction->months > 0) {
                            $amountToDeduct = $deduction->monthly_amortization / 2;
                        }

                        if ($amountToDeduct > $deduction->balance) {
                            $amountToDeduct = $deduction->balance;
                        }

                        $computedNetPay = $netPayAmount + $amountToDeduct;

                        if ($computedNetPay >= 1000) {
                            $totalDeductions -= $amountToDeduct;
                            $netPayAmount = $computedNetPay;

                            $removedDeductions[] = $deduction->deduction->id;

                            // $deduction->delete();
                            break;
                        } else {
                            $totalDeductions -= $amountToDeduct;
                            $netPayAmount += $amountToDeduct;
        
                            $removedDeductions[] = $deduction->deduction->id;
        
                            // $deduction->delete();
                        }
                    }
                }
            }

            foreach ($deductions as $deduction) {
                if (!in_array($deduction["deduction_id"], $removedDeductions)) {
                    $newDeductions[] = $deduction;
                }
            }

            $payroll = Payroll::updateOrCreate(
                [
                "employee_id" => $employee->id,
                "cut_off" => $cutOff->cut_off,
                "start_date" => $cutOff->start_date,
                "end_date" => $cutOff->end_date,
                ],
                [
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
                "remarks" => $remarks
            ]);
            
            if ($cutOff->part == 1) {
                $incentives = $this->computeIncentives(
                    $cutOff,
                    $employee,
                    $timeKeepingSummary->branch
                );
            }

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

    public function computeStatutoryOnly (Employee $employee, $cutOff) {
        $payroll = null;
        try {
            DB::beginTransaction();

            $daily_rate = round($employee->dailySalary(), 2);
            $hourly_rate = $employee->schedule->schedule_type->name === "Compressed Schedule" 
                ? round($daily_rate / 9.5, 2)
                : round($daily_rate / 8, 2);
            $minute_rate = $hourly_rate / 60;

            $paymentType = $employee->current_position->timekeeping_type;
            $cutOffBaseSalary = $employee->cutOffBaseSalary();
            $baseRegularDays = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13;
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
            $taxableAdustmentAmount = 0;
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
            $taxableIncome = 0;
            $remarks = null;

            $contributionTotal = 0;
            $additionalWorkingHoursTotal = 0;
            $workingHoursDeductionTotal = 0;
 
            $payrollRollListEmployee = PayrollList::where("employee_id", $employee->id)->first();

            if ($payrollRollListEmployee->isMancom()) {
                $cutOffBaseSalary = $employee->mancomCutOffBaseSalary();
            }

            $basicSalary = $cutOffBaseSalary;

            if ($employee->hasCompleteStatutories()) {
                $philHealthAmount = round(( $basicSalary * 0.05) / 2 , 2);
    
                $pagibig = Pagibig::where('min_amount', '<=', $basicSalary)
                ->where('max_amount', '>=', $basicSalary)
                ->first();
    
                $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                $pagibigAmount = $pagibigShares > $pagibig->max_deduction ? $pagibig->max_deduction : $pagibigShares;
                $pagibigEmployee = round($pagibigAmount / 2, 2);
                $pagibigEmployer = round($pagibigAmount / 2, 2);
    
                $sss = SSS::where('salary_range_min', '<=', $basicSalary)
                ->where('salary_range_max', '>=', $basicSalary)
                ->first();
    
                $sssEmployeeAmount = round($sss->employee_ss, 2);
                $sssEmployerAmount = round($sss->employer_ss, 2);
            }

            if ($payrollRollListEmployee->isOnMaternityLeave()) {
                $basicSalary = 0;
            }

            $contributionTotal = $sssEmployeeAmount + $pagibigEmployee + $philHealthAmount;

            $totalDeductions = 0;

            if ($payrollRollListEmployee->isMancom()) {
                $taxableIncome = round($basicSalary - $contributionTotal, 2);

                $tax = Tax::where("range_min", '<=' ,round($taxableIncome, 2))
                ->where("range_max", '>=',round($taxableIncome, 2))
                ->first();

                $salaryBase = $tax->salary_base ?? 0;
                $exemption = $tax->exemption ?? 0;
                $percentage = $tax->percentage ?? 0;

                $taxAmount = (($taxableIncome - $salaryBase) * ($percentage* 0.01)) + $exemption > 0
                    ? round( (($taxableIncome - $salaryBase) * ($percentage * 0.01)) + $exemption, 2)
                    : 0;

                $netPayAmount = round($taxableIncome - $taxAmount, 2);
            }

            Payroll::updateOrCreate(
                [
                "employee_id" => $employee->id,
                "cut_off" => $cutOff->cut_off,
                "start_date" => $cutOff->start_date,
                "end_date" => $cutOff->end_date,
                ],
                [
                "timekeeping_summary_id" => 0,
                "timekeeping_branch_id" => $employee->branch_id,
                "pay_date" => $cutOff->pay_date,
                "daily_rate" => $daily_rate,
                "hourly_rate" => $hourly_rate,
                "pay_type" => $paymentType,
                "tax_status" => $employee->tax_type,
                "salary" => $basicSalary,
                "tardiness_amount" => $tardinessAmount,
                "undertime_amount" => $undertimeAmount,
                "overtime_amount" => $overtimeAmount,
                "legal_holiday_amount" => $legalHolidayAmount,
                "legal_holiday_overtime_amount" => $legalHolidayOvertimeAmount,
                "special_holiday_amount" => $specialHolidayAmount,
                "special_holiday_overtime_amount" => $specialHolidayOvertimeAmount,
                "night_differential_amount" => $nightDifferentialAmount,
                "night_differential_overtime_amount" => $nightDifferentialOvertimeAmount,
                "taxable_adjustment" => $taxableAdustmentAmount,
                "non_taxable_adjustment" => $nonTaxableAmount,
                "gross_pay" => $grossPayAmount,
                "employee_sss" => $sssEmployeeAmount,
                "employer_sss" => $sssEmployerAmount,
                "employee_pagibig" => $pagibigEmployee,
                "employer_pagibig" => $pagibigEmployer,
                "employee_philhealth" => $philHealthAmount,
                "employer_philhealth" => $philHealthAmount,
                "withholding_tax" => $taxAmount,
                "deductions" => json_encode([]),
                "total_deductions" => $totalDeductions,
                "manual_deductions" => $manualDeductions,
                "cancelled_off" => $cancelledOffAmount,
                "net_pay" => $netPayAmount,
                "taxable_income" => $taxableIncome,
                "contribution_total" => $contributionTotal,
                "additional_working_hours_total" => $additionalWorkingHoursTotal,
                "working_hours_deduction_total" => $workingHoursDeductionTotal,
                "status" => "initial",
                "remarks" => $remarks
            ]);

            $this->computeIncentives($cutOff, $employee, $employee->current_branch);
            DB::commit();
            return [
                "error" => false,
                "payroll" => $payroll
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("ERROR COMPUTING PAYROLL STATUTORY TIMEKEEPING OF EMPLOYEE: " .$employee->full_name. " ERROR: ". $e->getMessage());
            
            return [
                "error" => true,
                "payroll" => $payroll
            ];
        }
    }

    public function computeIncentives(Cutoff $cutOff, Employee $employee, Branch $branch) {
        $incentivesAmount = 0;

        $position = $employee->current_position;
        $formerIncentives = Incentives::where("employee_id", $employee->id)
        ->orderBy("pay_date", "DESC")
        ->first();

        if ($formerIncentives && Carbon::parse($cutOff->start_date)->day === 15) {
            $position = Position::where("id", $formerIncentives->position_id)->first();
        }

        if ($position->rank == 1) {
            $months = Carbon::now()->diffInMonths($employee->date_hired);
            $bracket = IncentivesBracket::where("month", "<=", $months)
                ->whereHas('positions', function ($query) use ($employee) {
                    $query->where('positions.id', $employee->position_id);
                })
                ->orderBy("month", "DESC")
                ->first();

            if ($bracket) {
                $incentivesAmount = $bracket->amount;
            }
            
            if (Carbon::parse($employee->date_hired)->greaterThanOrEqualTo(Carbon::now()->subYears(2))) {
                $rankAndFileIncentive = IncentivesPerEmployee::where("employee_id", $employee->id)->first();

                if ($rankAndFileIncentive) {
                    $incentivesAmount = $rankAndFileIncentive->max_amount;
                }
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
        $status = "initial";

        //cutoff 26-10
        if ($cutOff->part == 1) {
            
            $startDate = Carbon::parse($cutOff->start_date)->subMonths(1)->day(26)->format("Y-m-d");
            $endDate = Carbon::parse($cutOff->start_date)->day(25)->format("Y-m-d");

            $firstCutOff = $this->getCutOffForDate($startDate);
            $secondCutOff = $this->getCutOffForDate($endDate);
            
            $firstTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
            ->where("cut_off", $firstCutOff["start_time"]."-".$firstCutOff["end_time"])
            ->first();

            $secondTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
            ->where("cut_off", $secondCutOff["start_time"]."-".$secondCutOff["end_time"])
            ->first();

            $firstCutOffDays = $firstTimeKeeping->regular_days ?? 0;
            $secondCutOffDays = $secondTimeKeeping->regular_days ?? 0;

            $days = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13; 

            $firstCutOffWorkDays = $employee->getCutOffWorkDays($firstCutOff);
            $secondCutOffWorkDays = $employee->getCutOffWorkDays($secondCutOff);

            $monthShouldBeWorkDays = ($firstCutOffWorkDays > 0 ? $firstCutOffWorkDays : $days)
            + ($secondCutOffWorkDays > 0 ? $secondCutOffWorkDays : $days);
            
            $monthWorkedDays = $firstCutOffDays + $secondCutOffDays;

            if ($firstTimeKeeping && $firstTimeKeeping->adjustment_amount == 0) {
                $adjustments = Adjustment::whereBetween("date", [$firstCutOff['start_time'], $firstCutOff['end_time']])
                ->where("employee_id", $employee->id)
                ->whereIn("status", ["Approved", "Adjusted"])
                ->get();

                $hours = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;

                foreach ($adjustments as $adjustment) {
                    $timeKeeping = TimeKeeping::where("date", $adjustment->date)
                    ->where("employee_id", $employee->id)
                    ->first();

                    $adjustmentTimeKeeping = AdjustmentTimeKeeping::where("employee_id", $employee->id)
                    ->where("date", $adjustment->date)
                    ->first();

                    if (
                        $timeKeeping &&
                        $adjustmentTimeKeeping &&
                        $timeKeeping->final_worked_hours !== $adjustmentTimeKeeping->final_worked_hours
                    ) {
                        $tobeDeducted = 0;
                        if ($hours >= $timeKeeping->final_worked_hours) {
                            $tobeDeducted = 1;
                        } else if ($hours / 2 == $timeKeeping->final_worked_hours) {
                            $tobeDeducted = 0.5;
                        }
                        
                        $monthWorkedDays -= $tobeDeducted;

                        if ($hours >= $adjustmentTimeKeeping->final_worked_hours) {
                            $monthWorkedDays += 1;
                        } else if ($hours / 2 == $timeKeeping->final_worked_hours) {
                            $monthWorkedDays += 0.5;
                        }
                    }
                }
            }

            if ($secondTimeKeeping && $secondTimeKeeping->adjustment_amount == 0) {
                $adjustments = Adjustment::whereBetween("date", [$secondCutOff['start_time'], $secondCutOff['end_time']])
                ->where("employee_id", $employee->id)
                ->whereIn("status", ["Approved", "Adjusted"])
                ->get();
                
                $hours = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;

                foreach ($adjustments as $adjustment) {
                    $timeKeeping = TimeKeeping::where("date", $adjustment->date)
                    ->where("employee_id", $employee->id)
                    ->first();

                    $adjustmentTimeKeeping = AdjustmentTimeKeeping::where("employee_id", $employee->id)
                    ->where("date", $adjustment->date)
                    ->first();

                    if (
                        $timeKeeping &&
                        $adjustmentTimeKeeping &&
                        $timeKeeping->final_worked_hours !== $adjustmentTimeKeeping->final_worked_hours
                    ) {
                        $tobeDeducted = 0;
                        if ($hours >= $timeKeeping->final_worked_hours) {
                            $tobeDeducted = 1;
                        } else if ($hours / 2 == $timeKeeping->final_worked_hours) {
                            $tobeDeducted = 0.5;
                        }
                        
                        $monthWorkedDays -= $tobeDeducted;

                        if ($hours >= $adjustmentTimeKeeping->final_worked_hours) {
                            $monthWorkedDays += 1;
                        } else if ($hours / 2 == $timeKeeping->final_worked_hours) {
                            $monthWorkedDays += 0.5;
                        }
                    }
                }
            }

            if ($monthWorkedDays > 0 && $monthWorkedDays >= $monthShouldBeWorkDays) {
                $incentiveAmount = $incentivesAmount;
            } else if ($monthWorkedDays > 0 && $monthShouldBeWorkDays > $monthWorkedDays) {
                $absent = $monthShouldBeWorkDays - $monthWorkedDays;

                $dailyIncentives = $incentivesAmount / 26;

                if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
                    $dailyIncentives = $incentivesAmount / 22; 
                }

                if ($monthWorkedDays === $monthShouldBeWorkDays / 2) {
                    $incentiveAmount = $incentivesAmount / 2;
                } else {
                    $incentiveAmount = $incentivesAmount - ($absent * $dailyIncentives);
                }

                $adjustmentDays = $absent;
                $remarks = "With ".$absent." absents";
            }

            $tardinessDateBasis = Carbon::parse($payDate)->subMonths(2);

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
                            $remarks .= " and ".$totalUndertimeForMonth." minutes of undertime";
                        } else {
                            $remarks = "With ".$totalUndertimeForMonth." minutes of undertime";
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
                            $remarks .= " and ".$totalUndertimeForMonth." minutes of undertime";
                        } else {
                            $remarks = "With ".$totalUndertimeForMonth." minutes of undertime";
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
                "pay_date" => $payDate,
            ],[
                "branch_id" => $branchId,
                "incentive_amount" => $incentiveAmount,
                "max_amount" => $maxAmount,
                "remarks" => $remarks,
                "amount_rate" => $amountRate,
                "additional_incentive" => $additionalIncentive,
                "adjustment_days" => $adjustmentDays,
                "adjustment_percent" => $adjustmentPercent,
                "first_cut_off_worked_days" => $firstCutOffDays,
                "second_cut_off_worked_days" => $secondCutOffDays,
                "status" => $status
            ]);

            return $incentive;
        }
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
