<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Cutoff;
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
        $includedEmployees = PayrollList::with(["employee"])
        ->where("is_included", 1)
        ->where(function ($query) {
            $query->where("is_on_maternity_leave", 1)
            ->orWhere("is_mancom", 1);
        })
        ->get();

        foreach ($includedEmployees as $includedEmployee) {
            $employee = $includedEmployee->employee;
            $timeKeepingSummary = TimeKeepingSummary::with([
                "employee",
                "employee.current_position",
                "employee.schedule",
                "employee.schedule.schedule_type"
            ])
            ->where("cut_off", $this->cutOff->start_date."-".$this->cutOff->end_date)
            ->where("employee_id",  $employee->id)
            ->first();

            if ($employee->type === "Agency") {
                continue;
            }

            if  ($includedEmployee->isOnMaternityLeave() && $timeKeepingSummary) {
                continue;
            }

            if ($includedEmployee->isOnMaternityLeave() || $includedEmployee->isMancom()) {
                $this->payrollService->computeStatutoryOnly($employee, $this->cutOff);
            } else if ($timeKeepingSummary && !$includedEmployee->isOnMaternityLeave() && !$includedEmployee->isMancom()) {
                $this->payrollService->computePayroll($timeKeepingSummary);
            }
        }
        $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
