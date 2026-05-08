<?php

namespace App\Http\Services;

use App\Jobs\ComputeEmployeeTardiness;
use App\Models\Adjustment;
use App\Models\AdjustmentTimeKeeping;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\IncentivePosition;
use App\Models\Incentives;
use App\Models\IncentivesBracket;
use App\Models\IncentivesManagement;
use App\Models\IncentivesPerEmployee;
use App\Models\Pagibig;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollList;
use App\Models\PhilhealthPercentageBase;
use App\Models\Position;
use App\Models\Rate;
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
            $remarks = $payrollDetails->remarks ?? null;
            $specialLeave = 0;
            $taxableIncome = 0;

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
                if (!$existingPayroll || $existingPayroll->net_pay <= 0 || $payrollDetails->reload_deductions) {
                    $employeeDeductions = EmployeeDeduction::with(["deduction"])
                    ->where("employee_id", $employee->id)
                    ->where("balance", ">", 0.01)
                    ->where("start_date", "<=", $cutOff->end_date)
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
                    $deductions = $existingPayroll->deductions;

                    if ($edit) {
                        $deductions = $payrollDetails->deductions;
                    }
    
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

            if ($payrollDetails->is_complete_documents == 1 && !$payrollDetails->is_manual_statutory_entry) {
                $sssComputation = $this->computeSSS($grossPayAmount, $cutOff->part, $latestPayroll);
                $pagibigComputation = $this->computePAGIBIG($basicSalary, $cutOff->part, $latestPayroll);
                $philHealthComputation = $this->computePHILHEALTH($basicSalary, $cutOff->part, $latestPayroll);

                $sssEmployeeAmount = round($sssComputation['ee'], 2);
                $sssEmployerAmount = round($sssComputation['er'], 2);   

                $pagibigEmployee = round($pagibigComputation['ee'], 2);
                $pagibigEmployer = round($pagibigComputation['er'], 2);

                $philHealthAmount = round($philHealthComputation, 2);
            }

            if ($payrollDetails->is_manual_statutory_entry) {
                $sssEmployeeAmount = $payrollDetails->employee_sss;
                $sssEmployerAmount = $payrollDetails->employer_sss;
                $pagibigEmployee = $payrollDetails->employee_pagibig;
                $pagibigEmployer = $payrollDetails->employer_pagibig;
                $philHealthAmount = $payrollDetails->employee_philhealth;
            }

            $taxableIncome = round($grossPayAmount - $philHealthAmount - $pagibigEmployee - $sssEmployeeAmount, 2);

            if ($payrollDetails->is_complete_documents == 1 && !$payrollDetails->is_manual_statutory_entry) {
                if ($this->isNcrMinimum($employee) || $payrollDetails->is_on_maternity_leave == 1) {
                    $taxAmount = 0;
                } else {
                    $tax = Tax::where("range_min", '<=' ,round($taxableIncome, 2))
                    ->where("range_max", '>=',round($taxableIncome, 2))
                    ->first();
    
                    $salaryBase = $tax->salary_base ?? 0;
                    $exemption = $tax->exemption ?? 0;
                    $percentage = $tax->percentage ?? 0;
    
                    $taxAmount = (($taxableIncome - $salaryBase) * ($percentage* 0.01)) + $exemption > 0
                        ? round( (($taxableIncome - $salaryBase) * ($percentage * 0.01)) + $exemption, 2)
                        : 0;
                }
            }

            if ($payrollDetails->is_manual_statutory_entry) {
                $taxAmount = $payrollDetails->withholding_tax;
            }
            
            $contributionTotal = $sssEmployeeAmount + $pagibigEmployee + $philHealthAmount;
            $additionalWorkingHoursTotal = $overtimeAmount + $legalHolidayAmount + $legalHolidayOvertimeAmount + $specialHolidayAmount + $specialHolidayOvertimeAmount + $nightDifferentialAmount + $nightDifferentialOvertimeAmount + $cancelledOffAmount;
            $workingHoursDeductionTotal = $undertimeAmount + $tardinessAmount;
            $netPayAmount = $taxableIncome - $taxAmount;

            $removedDeductions = [];
            $newDeductions = [];

            $netPayCheck = $netPayAmount - $totalDeductions - $manualDeductions + $nonTaxableAmount;
            // $companyLoans = collect($deductions)->filter(fn($deduct) => !in_array($deduct['type'], ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]))
            // ->sortByDesc('deducted_amount');
            // $governmentLoans = collect($deductions)->filter(fn($deduct) => in_array($deduct['type'], ["SSS Loans", "Pag-ibig/HDMF Loans", "Pag-ibig MP2"]))
            // ->sortByDesc('deducted_amount');
            $netPayNoDeductions = $netPayAmount + $nonTaxableAmount;
            // $companyLoans = $companyLoans->values()->toArray();
            // $governmentLoans = $governmentLoans->values()->toArray();
            $deductions = collect($deductions)
            ->sortBy("deducted_amount")
            ->values()
            ->toArray();
            if ($netPayCheck < 0) {
                foreach ($deductions as $deduction) {
                    $netPayNoDeductions -= $deduction['deducted_amount'];

                    if ($netPayNoDeductions < 0) {
                        $totalDeductions -= $deduction['deducted_amount'];
                        $netPayNoDeductions += $deduction['deducted_amount'];
                        $removedDeductions[] = $deduction['deduction_id'];
                    }
                }

                // foreach ($governmentLoans as $deduction) {
                //     $netPayNoDeductions -= $deduction['deducted_amount'];

                //     if ($netPayNoDeductions < 0) {
                //         $totalDeductions -= $deduction['deducted_amount'];
                //         $netPayNoDeductions += $deduction['deducted_amount'];
                //         $removedDeductions[] = $deduction['deduction_id'];
                //     }
                // }
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
                "deductions" => $newDeductions,
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
            Log::error("ERROR COMPUTING PAYROLL EMPLOYEE: " .$employee->full_name. " ERROR: ". $e);
            return $payroll;
        }
    }

    public function computeSSS ($grossPay = 0, $part = 1, $firstPayroll) {
        $sss = SSS::where('salary_range_min', '<=', $grossPay)
        ->where('salary_range_max', '>=', $grossPay)
        ->first();

        $shares = [
            "er" => 0,
            "ee" => 0
        ];
        
        if ($grossPay == 0) {
            return $shares;
        }

        if ($part == 1) {
            if ($sss) {
                $shares['ee'] = round($sss->employee_ss, 2);
                $shares['er'] = round($sss->employer_ss + $sss->employer_cc, 2);
            }
        } else {
            if ($firstPayroll) {
                $totalGross = $grossPay + $firstPayroll->gross_pay;
                $sss = SSS::where('salary_range_min', '<=', $totalGross)
                ->where('salary_range_max', '>=', $totalGross)
                ->first();

                if ($sss) {
                    $shares['ee'] = round($sss->employee_ss - $firstPayroll->employee_sss, 2);
                    $shares['er'] = round(($sss->employer_ss + $sss->employer_cc) - $firstPayroll->employer_sss, 2);
                }
            } else {
                if ($sss) {
                    $shares['ee'] = round($sss->employee_ss, 2);
                    $shares['er'] = round($sss->employer_ss + $sss->employer_cc, 2);
                }
            }
        }

        return $shares;
    }

    public function computePAGIBIG ($basicSalary = 0, $part = 1, $firstPayroll) {
        $pagibig = Pagibig::where('min_amount', '<=', $basicSalary)
        ->where('max_amount', '>=', $basicSalary)
        ->first();

        $shares = [
            "er" => 0,
            "ee" => 0
        ];

        if ($basicSalary <= 0) {
            return $shares;
        }

        if ($part == 1) {
            if ($pagibig) {
                $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                $pagibigAmount = $pagibigShares > 49.99 ? $pagibig->max_deduction / 2: $pagibigShares;
    
                $shares['ee'] = round($pagibigAmount, 2);
                $shares['er'] = round($pagibigAmount, 2);
            }
        } else {
            if ($firstPayroll) {
                $totalBasic = $basicSalary + $firstPayroll->salary - ($firstPayroll->tardiness_amount + $firstPayroll->undertime_amount);
                $pagibig = Pagibig::where('min_amount', '<=', $totalBasic)
                ->where('max_amount', '>=', $totalBasic)
                ->first();

                if ($pagibig) {
                    $pagibigShares = $totalBasic * (0.01 * $pagibig->percentage);
                    $pagibigAmount = $pagibigShares > 49.99
                        ? $pagibig->max_deduction - $firstPayroll->employee_pagibig
                        : $pagibigShares - $firstPayroll->employee_pagibig;
    
                    $shares['ee'] = round($pagibigAmount, 2);
                    $shares['er'] = round($pagibigAmount, 2);
                }
            } else {
                if ($pagibig) {
                    $pagibigShares = $basicSalary * (0.01 * $pagibig->percentage);
                    $pagibigAmount = $pagibigShares > 49.99 ? $pagibig->max_deduction / 2: $pagibigShares;

                    $shares['ee'] = round($pagibigAmount, 2);
                    $shares['er'] = round($pagibigAmount, 2);
                }
            }
        }

        return $shares;
    }

    public function computePHILHEALTH ($basicSalary = 0, $part = 1, $firstPayroll) {
        $philHealth = PhilhealthPercentageBase::first();

        $amount = 0;

        $philHealthBaseAmount = round(($basicSalary * ($philHealth->percentage * 0.01)), 2);

        if ($part == 1) {
            if ($basicSalary <= $philHealth->philhealth_cap) {
                $philHealthBaseAmount = round(($philHealth->philhealth_cap * ($philHealth->percentage * 0.01)), 2);
            }

            if ($philHealthBaseAmount / 2 > $philHealth->philhealth_max) {
                $philHealthBaseAmount = $philHealth->philhealth_max * 2;
            }

            $philHealthAmount = round(($philHealthBaseAmount / 2), 2);

            if ($basicSalary <= 0 || $philHealthAmount < 0) {
                $philHealthAmount = 0;
            }

            $amount = $philHealthAmount;
        } else {
            if ($firstPayroll) {
                $totalBasic = $basicSalary + $firstPayroll->salary - ($firstPayroll->tardiness_amount + $firstPayroll->undertime_amount);
                $philHealthBaseAmount = round(($totalBasic * ($philHealth->percentage * 0.01)) , 2);

                if ($philHealthBaseAmount / 2 > $philHealth->philhealth_max) {
                    $philHealthBaseAmount = $philHealth->philhealth_max * 2;
                }

                if ($totalBasic <= $philHealth->philhealth_cap) {
                    $philHealthBaseAmount = round(($philHealth->philhealth_cap * ($philHealth->percentage * 0.01)), 2);
                }

                $totalPhilhealthAmount = round(($philHealthBaseAmount - ($firstPayroll->employee_philhealth + $firstPayroll->employer_philhealth)), 2);
                $philHealthAmount = round(($totalPhilhealthAmount / 2), 2);

                if ($basicSalary <= 0 || $philHealthAmount < 0) {
                    $philHealthAmount = 0;
                }

                $amount = $philHealthAmount;
            } else {
                if ($basicSalary <= $philHealth->philhealth_cap) {
                    $philHealthBaseAmount = round(($philHealth->philhealth_cap * ($philHealth->percentage * 0.01)), 2);
                }

                if ($philHealthBaseAmount / 2 > $philHealth->philhealth_max) {
                    $philHealthBaseAmount = $philHealth->philhealth_max * 2;
                }

                $philHealthAmount = round(($philHealthBaseAmount / 2), 2);

                if ($basicSalary <= 0 || $philHealthAmount < 0) {
                    $philHealthAmount = 0;
                }

                $amount = $philHealthAmount;
            }
        }
        
        return $amount;
    }

    public function computeIncentives(Cutoff $cutOff, Employee $employee, Branch $branch) {
        $incentivesAmount = 0;

        $startDate = Carbon::parse($cutOff->start_date)->subMonths(1)->day(26)->format("Y-m-d");
        $endDate = Carbon::parse($cutOff->start_date)->day(25)->format("Y-m-d");

        $firstCutOff = $this->getCutOffForDate($startDate);
        $secondCutOff = $this->getCutOffForDate($endDate);
        $currentCutOff = $this->getCutOffForDate($cutOff->pay_date);

        $month = Carbon::parse($secondCutOff['end_time'])->month;
        $year = Carbon::parse($secondCutOff['end_time'])->year;

        $payrollDetails = PayrollDetail::where("employee_id", $employee->id)
        ->whereMonth("pay_date", $month)
        ->whereYear("pay_date", $year)
        ->orderBy("pay_date", "ASC")
        ->take(2)
        ->get();

        $firstTimeKeeping = $payrollDetails->get(0);
        $secondTimeKeeping = $payrollDetails->get(1);

        if ($firstTimeKeeping) {
            $position = Position::find($firstTimeKeeping->position_id);
        }

        if ($secondTimeKeeping) {
            $position = Position::find($secondTimeKeeping->position_id);
        }

        if (!$position) {
            $position = $employee->current_position;
        }

        if ($position->rank == 1) {
            $months = Carbon::now()->diffInMonths($employee->date_hired);
            if ($months < 1) {
                $months = 1;
            }
            $bracket = IncentivesBracket::whereRaw('CAST(month AS UNSIGNED) <= ?', [$months])
                ->whereHas('positions', function ($query) use ($position) {
                    $query->where('positions.id', $position->id);
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
        $undertime = 0;
        $absent = 0;
        $baseAdditional = 0;
        $addedDays = 0;

        $firstCutOffDays = $firstTimeKeeping->regular_days + $firstTimeKeeping->special_leave;
        $secondCutOffDays = $secondTimeKeeping->regular_days + $secondTimeKeeping->special_leave;

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
            $absent = $adjustmentDays;
        }

        foreach ($firstCutOffAdjustments as $adjustment) {
            $additionalDays[] = [
                "date" => $adjustment->date,
                "days" => $adjustment->days
            ];
            $addedDays += $adjustment->days;
            $firstCutOffDays += $adjustment->days;
        }

        foreach ($secondCutOffAdjustments as $adjustment) {
            $additionalDays[] = [
                "date" => $adjustment->date,
                "days" => $adjustment->days
            ];
            $addedDays += $adjustment->days;
            $secondCutOffDays += $adjustment->days;
        }

        $monthWorkedDays = $firstCutOffDays + $secondCutOffDays;
        $monthShouldBeWorkDays = 26;
        $dailyIncentives = $incentivesAmount / 26;
        if ($employee->schedule->schedule_type->name === "Compressed Schedule") {
            $dailyIncentives = $incentivesAmount / 22; 
            $monthShouldBeWorkDays = 22;
        }

        $incentivePosition = IncentivePosition::where("position_id", $positionId)->first();
        if ($incentivePosition) {
            $incentivesAmount = $incentivePosition->amount;
            $maxAmount = $incentivePosition->amount;
            $amountRate = $incentivePosition->amount;
            $dailyIncentives = $incentivePosition->amount / $monthShouldBeWorkDays;

            if (trim($employee->oic_status) == "OIC") {
                $baseAdditional = $incentivePosition->oic_additional_amount;
                $additionalDailyRate = $baseAdditional / $monthShouldBeWorkDays;
                $additionalIncentive = $additionalDailyRate * ($monthWorkedDays);

                if ($additionalIncentive > $baseAdditional) {
                    $additionalIncentive = $baseAdditional;
                }
            }
        }

        if (str_contains($position->description, "Officer")) {
            $incentiveAmount = 0;
            $amountRate = 0;
            $dailyIncentives = 0;
            $maxAmount = 0;
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
                    $undertime = $excessiveUndertime;
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
                    $undertime = $totalUndertimeForMonth;
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

        $incentiveAmount += $additionalIncentive;
        
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
            "undertime" => $undertime,
            "absent" => $absent,
            "status" => $status,
            "base_additional_incentive" => $baseAdditional,
            "added_days" => $addedDays,
        ]);

        return $incentive;
    }

    private function isNcrMinimum(Employee $employee): bool
    {
        $ncrRate = Rate::where("code", "E")->first();
        $divisor = 26;
        $isMinimum = false;
        $branchLocation = $employee->home_branch?->location ?? '';
        $rateCategory = $employee->salary_position->rate_category;

        if ($employee->schedule->schedule_type->name == "Compressed Schedule") {
            $divisor = 22;
        }

        if (
            (str_contains($branchLocation, 'NCR') && ($employee->payroll_list->salary_amount / $divisor) == $ncrRate->minimum) ||
            (str_contains($branchLocation, 'NCR') && $rateCategory == 'MINIMUM')
        ) {
            $isMinimum = true;
        }
        

        return $isMinimum;
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
