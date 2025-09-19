<?php

namespace App\Http\Services;

use App\Models\Cutoff;
use App\Models\Pagibig;
use App\Models\Payroll;
use App\Models\SSS;
use App\Models\Tax;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;

class PayrollService {
    public function computePayroll (TimeKeepingSummary $timeKeepingSummary) {
        $payroll = null;
        try {
            DB::beginTransaction();

            $employee = $timeKeepingSummary->employee;
            $daily_rate = round($employee->daily_salary, 2);
            $hourly_rate = $employee->schedule->schedule_type->name === "Compressed Schedule" 
                ? round($daily_rate / 9.5, 2)
                : round($daily_rate / 8, 2);
            $minute_rate = $hourly_rate / 60;
            
            $pagibigRate = Pagibig::first();

            $cutOff = Cutoff::where("cut_off", $timeKeepingSummary->cut_off)->first();
            $paymentType = $employee->current_position->timekeeping_type;
            $cutOffBaseSalary = $employee->cut_off_base_salary;
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
            $taxableAdustmentAmount = $timeKeepingSummary->adjustment_amount;
            $philHealthAmount = 0;
            $pagibigEmployee = $pagibigRate->employee_share / 2;
            $pagibigEmployer = $pagibigRate->employer_share / 2;
            $sssEmployeeAmount = 0;
            $sssEmployerAmount = 0;
            $taxAmount = 0;
            $grossPayAmount = 0;
            $netPayAmount = 0;
            $deductionAmount = 0;
            $totalDeductions = 0;
            $manualDeductions = 0;

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
                $legalHolidayAmount += (float)$payment->legal_holiday;
                $legalHolidayOvertimeAmount += (float)$payment->legal_holiday_overtime;
                $specialHolidayAmount += (float)$payment->special_holiday;
                $specialHolidayOvertimeAmount += (float)$payment->special_holiday_overtime;
                $nightDifferentialAmount += (float)$payment->night_differential;
                $nightDifferentialOvertimeAmount += (float)$payment->night_differential_overtime;
                $cancelledOffAmount += (float)$payment->cancelled_off;
            }

            $salary += $cancelledOffAmount;
            $basicSalary = $salary - ($tardinessAmount + $undertimeAmount);
            $philHealthAmount = round(( $basicSalary * 0.05) / 2 , 2);

            $sss = SSS::where('salary_range_min', '<=', $basicSalary)
            ->where('salary_range_max', '>=', $basicSalary)
            ->first();

            $sssEmployeeAmount = round($sss->employee_ss, 2);
            $sssEmployerAmount = round($sss->employer_ss, 2);

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

            $taxableIncome = $grossPayAmount - $philHealthAmount - $pagibigEmployee - $sssEmployeeAmount;
            $tax = Tax::where("range_min", '<=' ,round($taxableIncome, 2))
            ->where("range_max", '>=',round($taxableIncome, 2))
            ->first();

            $salaryBase = $tax->salary_base;
            $exemption = $tax->exemption;
            $percentage = $tax->percentage;

            // $taxAmount = (($taxableIncome - $tax->salary_base) +$tax->exemption) * ($tax->percentage * 0.01) > 0
            //     ? (($taxableIncome - $tax->salary_base) +$tax->exemption) * ($tax->percentage * 0.01)
            //     : 0;

            $taxAmount = ((($taxableIncome - $salaryBase) + $exemption) * ($percentage* 0.01)) > 0
                ? round( (($taxableIncome - $salaryBase) + $exemption) * ($percentage * 0.01), 2)
                : 0;

            $totalDeductions = $sssEmployeeAmount + $pagibigEmployee + $philHealthAmount + $taxAmount;

            $netPayAmount = $taxableIncome - $taxAmount;

            Payroll::updateOrCreate(
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
                "salary" => $salary,
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
                "net_pay" => $netPayAmount,
                "status" => "initial",
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("ERROR COMPUTING PAYROLL TIMEKEEPING: " .$timeKeepingSummary->id." ERROR: ". $e);
            
            return [
                "error" => true,
                "payroll" => $payroll
            ];
        }
    }
}