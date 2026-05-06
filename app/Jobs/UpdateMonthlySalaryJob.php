<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\Rate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateMonthlySalaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected string $rateCode;
    /**
     * Create a new job instance.
     */
    public function __construct(string $rateCode)
    {
        $this->rateCode = $rateCode; 
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $rateCode = $this->rateCode;
        $rate = Rate::where("code", $rateCode)->first();
        $dailyRate = 0;
        if ($rate) {
            $dailyRate = $rate->minimum;
        }
        $employees = Employee::with(["payroll_list", "schedule.schedule_type"])
        ->where("status", "active")
        ->whereHas("home_branch", function ($query) use($rateCode) {
            $query->where("rate_code", $rateCode);
        })
        ->whereHas("salary_position", function ($query) {
            $query->where("rate_category", "MINIMUM");
        })
        ->get();

        foreach ($employees as $employee) {
            $multiplier = 26;
            $scheduleType = $employee->schedule->schedule_type->name;
            if ($scheduleType == "Compressed Schedule") {
                $multiplier = 22;
            }
            $payrollList = $employee->payroll_list;

            if ($payrollList) {
                $payrollList->update([
                    "salary_amount" => round(($dailyRate * $multiplier),2)
                ]);
            }
        }
    }
}
