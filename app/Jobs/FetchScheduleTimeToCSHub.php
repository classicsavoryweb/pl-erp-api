<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use DB;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchScheduleTimeToCSHub implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $timeKeepingBranch;
    protected $activeCutOff;
    /**
     * Create a new job instance.
     */
    public function __construct(Branch $timeKeepingBranch, Cutoff $activeCutOff)
    {
        $this->timeKeepingBranch = $timeKeepingBranch;
        $this->activeCutOff = $activeCutOff;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $branch = $this->timeKeepingBranch;
        $activeCutOff = $this->activeCutOff;

        $start_date = $activeCutOff->start_date;
        $end_date = $activeCutOff->end_date;

        $scheduleLink = env('CS_HUB_URL_LINK')."/get-employee-assigned-schedules";
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');

        $response = Http::withBasicAuth($user, $password)
        ->withHeaders([
            "Accept" => "application/json",
            "Content-Type" => "application/json"
        ])->timeout(60)
        ->retry(3, 5000)
        ->get($scheduleLink,[
            "start_date" => $start_date,
            "end_date" => $end_date,
            "branch_short_name" => $branch->short_name
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $schedules = $data['data']['schedules'];

            try {
                DB::beginTransaction();
                foreach ($schedules as $schedule) {
                    $employee = Employee::where("employee_number", $schedule["employee_number"])
                    ->first();

                    $sched = ScheduleMasterlist::where("selection", $schedule["schedule_name"])
                    ->first();

                    if ($employee && $sched) {
                        ScheduleTime::updateOrCreate(
                            [
                                "employee_id" => $employee->id,
                                "biometric_number" => $employee->biometric_number,
                                "date" => $schedule["work_date"],
                                "branch_id" => $branch->id,
                            ], [
                                "timetable" => $sched->selection,
                                "check_in" => $sched->start_time,
                                "check_out" => $sched->end_time
                            ]
                        );
                    }
                }
                DB::commit(); 
            } catch (Exception $e) {
                DB::rollBack();
            }
        }
    }
}
