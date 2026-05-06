<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Cutoff;
use App\Models\Payroll;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateIncentiveAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $endDate;
    protected $payrollService;

    public function __construct($endDate)
    {
        $this->endDate = $endDate;
        $this->payrollService = new PayrollService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $endDate = $this->endDate;
        $startDate = Carbon::parse($endDate)->subMonth()->day(26)->format("Y-m-d");
        $firstCutOff = Cutoff::where("start_date", $startDate)->first();
        $secondCutOff = Cutoff::where("end_date", $endDate)->first();

        $payrolls = Payroll::whereIn("pay_date", [$firstCutOff->pay_date, $secondCutOff->pay_date])
        ->with(['employee', 'employee.current_position', 'timekeeping_branch'])
        ->distinct('employee_id')
        ->get();

        foreach ($payrolls as $payroll) {
            $employee = $payroll->employee;

            $this->payrollService->computeIncentives(
                $secondCutOff,
                $employee,
                $payroll->timekeeping_branch
            );
        }
    }
}
