<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Adjustment;
use App\Models\Cutoff;
use App\Models\DeductionHistory;
use App\Models\EmployeeDeduction;
use App\Models\Payroll;
use App\Models\TimeKeepingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFinalizeCutOff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected Cutoff $cutOff;
    /**
     * Create a new job instance.
     */
    public function __construct(Cutoff $cutoff)
    {
        $this->cutOff = $cutoff;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payrolls = Payroll::where("cut_off", $this->cutOff->cut_off)
        ->where("status", "initial")
        ->get();

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
    }
}
