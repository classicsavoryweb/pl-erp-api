<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Cutoff;
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

class ProcessInitializeCutOff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;
    protected Cutoff $cutOff;
    protected PayrollService $payrollService;
    protected $userId;
    protected $notificationLabel = "Initialize Cutoff";
    protected $notificationContext;
    /**
     * Create a new job instance.
     */
    public function __construct(Cutoff $cutoff, ?int $userId = null, ?string $notificationContext = null)
    {
        $this->cutOff = $cutoff;
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
        $cutOff = $this->cutOff;

        $includedEmployees = PayrollList::with([
            "employee",
            "employee.schedule",
            "employee.schedule.schedule_type"
        ])
        ->where("is_included", 1)
        ->get();

        foreach ($includedEmployees as $payrollList) {
            $divisor = 13;
            $dayDivisor = 8;
            $salaryBaseAmount = $payrollList->salary_amount ?? 0;
            $employee = $payrollList->employee;
            $scheduleName = $payrollList->employee->schedule?->schedule_type?->name ?? "Normal Schedule";

            if ($scheduleName == "Compressed Schedule") {
                $divisor = 11;
                $dayDivisor = 9.5;
            }

            $dailyRate = $salaryBaseAmount / ($divisor * 2);
            $hourlyRate = $dailyRate / $dayDivisor;
            $minuteRate = $hourlyRate / 60; 

            PayrollDetail::updateOrCreate([
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
            ]);
        }

        $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
