<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Branch;
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
        $history = json_decode($cutOff->initializations, true);
        $initializationDates = collect($history)
        ->pluck('initialized_at')
        ->sortDesc()
        ->values();
        $datesArray = $initializationDates->toArray();
        $count = count($datesArray);

        $includedEmployees = PayrollList::with([
            "employee",
            "employee.schedule",
            "employee.schedule.schedule_type"
        ])
        ->where("is_included", 1);

        if ($count > 1) {
            $dateBasis = $datesArray[$count - 2];
            $includedEmployees = $includedEmployees->where("updated_at", ">=", $dateBasis);
        }

        $includedEmployees = $includedEmployees->get();

        foreach ($includedEmployees as $payrollList) {
            $divisor = 13;
            $dayDivisor = 8;
            $salaryBaseAmount = $payrollList->salary_amount ?? 0;
            $employee = $payrollList->employee;
            $scheduleName = $payrollList->employee->schedule?->schedule_type?->name ?? "Normal Schedule";

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
                "daily_rate" => round($dailyRate, 2),
                "hourly_rate" => round($hourlyRate, 2),
                "minute_rate" => round($minuteRate, 2),
                "schedule_type" => $scheduleName,
            ]);

            if (
                $payrollDetails->is_mancom == 1 ||
                $payrollDetails->is_on_maternity_leave == 1
            ) {
                $payrollDetails->update([
                    "regular_days" => $days,
                ]);

                $comp = $this->payrollService->computePayrollV2($payrollDetails ,$cutOff);
                $branchIdToUse = $employee->branch_id;

                if ($payrollDetails->is_on_maternity_leave == 1) {
                    $hoBranch = Branch::where("short_name", "HO")->first();
                    $branchIdToUse = $hoBranch->id;
                }

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
            }
        }

        $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
