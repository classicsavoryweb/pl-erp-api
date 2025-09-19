<?php

namespace App\Jobs;

use App\Http\Services\PayrollService;
use App\Models\Cutoff;
use App\Models\TimeKeepingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInitializeCutOff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected Cutoff $cutOff;
    protected PayrollService $payrollService;
    /**
     * Create a new job instance.
     */
    public function __construct(Cutoff $cutoff)
    {
        $this->cutOff = $cutoff;
        $this->payrollService = new PayrollService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $timeKeepingSummaries = TimeKeepingSummary::with(["employee"])
        ->where("cut_off", $this->cutOff->start_date."-".$this->cutOff->end_date)
        ->get();

        foreach ($timeKeepingSummaries as $timeKeepingSummary) {
            $this->payrollService->computePayroll($timeKeepingSummary);
        }
    }
}
