<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Adjustment;
use App\Models\Cutoff;
use App\Models\DeductionHistory;
use App\Models\EmployeeDeduction;
use App\Models\Incentives;
use App\Models\Payroll;
use App\Models\TimeKeepingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\NotifiesJobStatus;

class ProcessFinalizeCutOff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;
    protected Cutoff $cutOff;
    protected $user;
    protected $notificationLabel = "Finalize Cutoff";
    protected $notificationContext;
    /**
     * Create a new job instance.
     */
    public function __construct(Cutoff $cutoff, $user, ?string $notificationContext = null)
    {
        $this->cutOff = $cutoff;
        $this->user = $user;
        $this->notificationContext = $notificationContext;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
        $payrolls = Payroll::where("cut_off", $this->cutOff->cut_off)
        ->where("status", "initial")
        ->get();

        $incentives = Incentives::where("pay_date", $this->cutOff->pay_date)
        ->where("status", "initial")
        ->get();

        $pay_date = $this->cutOff->pay_date;
        $start_date = $this->cutOff->start_date;
        $end_Date = $this->cutOff->end_date;

        foreach($payrolls as $payroll) {
            $payroll->update([
                "status" => "final",
            ]);

            $deductions = json_decode($payroll->deductions, true);
            $adjustments = Adjustment::where("employee_id", $payroll->empployee_id)
            ->where("status", "Adjusted")
            ->get();

            foreach ($adjustments as $adjustment) {
                $adjustment->update([
                    "is_timekeeping_locked" => true
                ]);
            }

            foreach ($deductions as $deduction) {
                $employeeDeduction = EmployeeDeduction::find($deduction["deduction_id"]);

                if (!$employeeDeduction){
                    continue;
                }

                $newBalance = $employeeDeduction->balance - $deduction["deducted_amount"];

                $employeeDeduction->update([
                    "balance" => $newBalance
                ]);

                DeductionHistory::create([
                    "employee_id" => $employeeDeduction->employee_id,
                    "deduction_id" => $employeeDeduction->dedcution_id,
                    "reference_doc" => $employeeDeduction->reference_doc,
                    "amount" => $deduction["deducted_amount"],
                    "pay_date" => $payroll->pay_date,
                    "date_time" => now()
                ]);
            }
        }

        foreach ($incentives as $incentive) {
            $incentive->update([
                "status" => "final"
            ]);
        }

        GenerateEntry::dispatch($payrolls, $pay_date, $start_date, $end_Date, $this->user);
        $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
