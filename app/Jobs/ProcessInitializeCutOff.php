<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Incentives;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollList;
use App\Models\TimeKeepingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\NotifiesJobStatus;
use Illuminate\Support\Facades\Log;

class ProcessInitializeCutOff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;
    protected $branchId;
    protected PayrollService $payrollService;
    protected $userId;
    protected $notificationContext;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $branchId = null, ?int $userId = null, ?string $notificationContext = null)
    {
        $this->branchId = $branchId;
        $this->payrollService = new PayrollService();
        $this->userId = $userId;
        $this->notificationContext = $notificationContext;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
        $branchId = $this->branchId;
        $cutOff = Cutoff::where("status", "active")->first();

        $includedEmployees = Employee::with([
            "payroll_list",
            "schedule",
            "schedule.schedule_type"
        ])
        ->where(function ($query) use ($branchId, $cutOff) {
            $query->whereHas("payrolls", function ($q) use ($branchId, $cutOff) {
                $q->where("pay_date", $cutOff->pay_date)
                ->where("timekeeping_branch_id", $branchId);
            })
            ->orWhere("branch_id", $branchId);
        })->whereHas("payroll_list", function ($query) {
            $query->where("is_included", 1);
        })->get();

        $payrollIds = Payroll::where("pay_date", $cutOff->pay_date)
        ->where("timekeeping_branch_id", $branchId)
        ->pluck("id");

        Payroll::whereIn("id", $payrollIds)->delete();
        PayrollDetail::whereIn("payroll_id", $payrollIds)->delete();

        $hoBranch = Branch::where("short_name", "HO")->first();
        foreach ($includedEmployees as $employee) {
            $payrollList = $employee->payroll_list;
            $divisor = 13;
            $dayDivisor = 8;
            $salaryBaseAmount = $payrollList->salary_amount ?? 0;
            // $employee = $payrollList->employee;
            $scheduleName = $employee->schedule->schedule_type->name ?? "Normal Schedule";
            $branchIdToUse = $employee->branch_id;

            if ($payrollList->is_on_maternity_leave == 1 || $payrollList->is_mancom == 1) {
                $branchIdToUse = $hoBranch->id;
            }

            $days = 13;

            if ($scheduleName == "Compressed Schedule") {
                $divisor = 11;
                $dayDivisor = 9.5;
                $days = 11;
            }

            $dailyRate = $salaryBaseAmount / ($divisor * 2);
            $hourlyRate = $dailyRate / $dayDivisor;
            $minuteRate = $hourlyRate / 60;

            $payrollDetails = PayrollDetail::updateOrCreate([
                "employee_id" => $employee->id,
                "pay_date" => $cutOff->pay_date,
            ], [
                "home_branch_id" => $employee->home_branch_id,
                "position_id" => $employee->position_id,
                "is_hold" => $payrollList->is_hold,
                "is_on_maternity_leave" => $payrollList->is_on_maternity_leave,
                "is_mancom" => $payrollList->is_mancom,
                "is_complete_documents" => $payrollList->has_complete_documents,
                "daily_rate" => $dailyRate,
                "hourly_rate" => $hourlyRate,
                "minute_rate" => $minuteRate,
                "schedule_type" => $scheduleName,
            ]);

            if (
                $payrollList->is_mancom == 1 ||
                $payrollList->is_on_maternity_leave == 1
            ) {
                $payrollDetails->update([
                    "regular_days" => $days,
                ]);

                $comp = $this->payrollService->computePayrollV2($payrollDetails ,$cutOff);

                if ($comp) {
                    $payroll = Payroll::updateOrCreate([
                        "employee_id" => $employee->id,
                        "cut_off" => $cutOff->cut_off,
                        "start_date" => $cutOff->start_date,
                        "end_date" => $cutOff->end_date,
                        
                    ], 
                    array_merge(
                        [
                            "tax_status" => $employee->tax_type,
                            "pay_type" => $employee->current_position->timekeeping_type,
                            "pay_date" => $cutOff->pay_date,
                            "daily_rate" => $payrollDetails->daily_rate,
                            "hourly_rate" => $payrollDetails->hourly_rate,
                            "timekeeping_branch_id" => $branchIdToUse,
                            "position_id" => $payrollDetails->position_id,
                            "timekeeping_summary_id" => 0,
                        ],
                        (array) $comp
                    )
                    );

                    $payrollDetails->update([
                        "payroll_id" => $payroll->id
                    ]);
                }
            } else {
                $payroll = Payroll::updateOrCreate([
                    "employee_id" => $employee->id,
                    "cut_off" => $cutOff->cut_off,
                    "start_date" => $cutOff->start_date,
                    "end_date" => $cutOff->end_date,
                    
                ], [
                    "tax_status" => $employee->tax_type,
                    "pay_type" => $employee->current_position->timekeeping_type,
                    "pay_date" => $cutOff->pay_date,
                    "daily_rate" => $payrollDetails->daily_rate,
                    "hourly_rate" => $payrollDetails->hourly_rate,
                    "timekeeping_branch_id" => $branchIdToUse,
                    "position_id" => $payrollDetails->position_id,
                    "timekeeping_summary_id" => 0,
                    "salary" => 0,
                    "tardiness_amount" => 0,
                    "undertime_amount" => 0,
                    "overtime_amount" => 0,
                    "legal_holiday_amount" => 0,
                    "legal_holiday_overtime_amount" => 0,
                    "special_holiday_amount" => 0,
                    "special_holiday_overtime_amount" => 0,
                    "night_differential_amount" => 0,
                    "night_differential_overtime_amount" => 0,
                    "taxable_adjustment" => 0,
                    "non_taxable_adjustment" => 0,
                    "gross_pay" => 0,
                    "employee_sss" => 0,
                    "employer_sss" => 0,
                    "employee_pagibig" => 0,
                    "employer_pagibig" => 0,
                    "employee_philhealth" => 0,
                    "employer_philhealth" => 0,
                    "withholding_tax" => 0,
                    "deductions" => json_encode([]),
                    "total_deductions" => 0,
                    "manual_deductions" => 0,
                    "cancelled_off" => 0,
                    "net_pay" => 0,
                    "taxable_income" => 0,
                    "contribution_total" => 0,
                    "additional_working_hours_total" => 0,
                    "working_hours_deduction_total" => 0,
                    "status" => "initial",
                    "is_maternity" => $payrollDetails->is_on_maternity_leave,
                    "is_hold" => $payrollDetails->is_hold,
                    "remarks" => "",
                    "special_leave" => 0
                ]);

                $payrollDetails->update([
                    "payroll_id" => $payroll->id
                ]);
            }
        }

        $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
