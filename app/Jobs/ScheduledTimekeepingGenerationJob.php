<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\ScheduledTimekeepingGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class ScheduledTimekeepingGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cutOffId;
    protected $scheduleId;
    public function __construct($cutOffId, $scheduleId)
    {
        $this->cutOffId = $cutOffId;
        $this->scheduleId = $scheduleId;
    }

    public function handle(): void
    {
        $schedule = ScheduledTimekeepingGeneration::find($this->scheduleId);
        $activeCutOff = Cutoff::find($this->cutOffId);

        if ($schedule) {
            $schedule->update([
                "status" => "Dispatched"
            ]);
        }

        $branches = Branch::where("status", "active")->get();

        //schedule
        foreach ($branches as $branch) {
            FetchScheduleTimeToCSHub::dispatch($branch, $activeCutOff);
        }
        //overtime
        foreach ($branches as $branch) {
            FetchOvertimeJob::dispatch($branch->id);
        }
        //logs
        foreach ($branches as $branch) {
            FetchBranchLogs::dispatch($branch->id);
        }
        //timekeeping
        foreach ($branches as $branch) {
            Bus::chain([
                new TransferLogsToTimeKeeping($branch->id, $activeCutOff),
                new GenerateTimekeepingSummary($branch->id, $activeCutOff)
            ])
            ->dispatch();
        }
    }
}
