<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Cutoff;
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

        $timeKeepings = TimeKeepingSummary::whereIn("cut_off", [$firstCutOff->cut_off, $secondCutOff->cut_off])
        ->with(['employee', 'employee.current_position', 'branch'])
        ->distinct('employee_id')
        ->get();

        foreach ($timeKeepings as $timeKeeping) {
            $employee = $timeKeeping->employee;

            $this->payrollService->computeIncentives(
                $secondCutOff,
                $employee,
                $timeKeeping->branch
            );
        }
    }
}
