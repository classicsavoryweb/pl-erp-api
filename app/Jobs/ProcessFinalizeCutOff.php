<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Adjustment;
use App\Models\Cutoff;
use App\Models\DeductionHistory;
use App\Models\EmployeeDeduction;
use App\Models\Incentives;

use App\Models\Payroll;
use App\Models\PayrollList;
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

            $start_date = $this->cutOff->start_date;
            $end_Date = $this->cutOff->end_date;

            foreach($payrolls as $payroll) {
                $payroll->update([
                    "status" => "final",
                ]);

                $deductions = $payroll->deductions;
                $adjustments = Adjustment::where("employee_id", $payroll->employee_id)
                ->where("is_timekeeping_locked", "0")
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

                    $newBalance = $employeeDeduction->balance;
                    $oldBalance = $employeeDeduction->balance;

                    if ($employeeDeduction->balance > 0) {
                        $newBalance = $employeeDeduction->balance - $deduction["deducted_amount"];
                        $employeeDeduction->update([
                            "balance" => $newBalance,
                            // "end_loan_status" => $newBalance == 0 ? "Yes" : "No"
                        ]);
                    }

                    DeductionHistory::create([
                        "employee_id" => $employeeDeduction->employee_id,
                        "deduction_id" => $employeeDeduction->deduction_id,
                        "reference_doc" => $employeeDeduction->reference_doc,
                        "amount" => $deduction["deducted_amount"],
                        "pay_date" => $payroll->pay_date,
                        "date_time" => now(),
                        "old_balance" => $oldBalance,
                        "new_balance" => $newBalance,
                    ]);
                }
            }

            $cutoffValue = $this->cutOff->cut_off;

            $payrollLists = PayrollList::where('is_hold', 1)->get();

            foreach ($payrollLists as $payrollList) {

                $holdedCutoffs = [];

                if (!empty($payrollList->holded_cutoffs)) {
                    $holdedCutoffs = json_decode($payrollList->holded_cutoffs, true) ?? [];
                }

                if (!in_array($cutoffValue, $holdedCutoffs)) {
                    $holdedCutoffs[] = $cutoffValue;
                }

                $payrollList->update([
                    'holded_cutoffs' => json_encode($holdedCutoffs),
                ]);
            }
            
            UpdateOvertimeStatusJob::dispatch([
                "start_time" => $start_date,
                "end_time" => $end_Date,
            ]);

            if ($this->cutOff->part == 2) {
                ComputeEmployeeTardiness::dispatch($this->cutOff->end_date);
                GenerateIncentiveAttendanceJob::dispatch($this->cutOff->end_date);
            }

            // GenerateEntry::dispatch($payrolls, $pay_date, $start_date, $end_Date, $this->user);
            $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
