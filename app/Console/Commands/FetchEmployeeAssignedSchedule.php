<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Http;
use Log;

class FetchEmployeeAssignedSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-employee-assigned-schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching employee assigned schedule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $scheduleLink = env('CS_HUB_URL_LINK')."/get-employee-assigned-schedules";
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');

        $branches = Branch::where("status", "active")->get();
        

        $now = Carbon::now();
        $startDate = $now->copy()
        ->startOfMonth()
        ->addDays(10)
        ->startOfDay();
        $endDate = $now->copy()
        ->startOfMonth()
        ->addDays(24)
        ->endOfDay();
        $dayOfMonth = Carbon::now()->day;

        if ($dayOfMonth >= 1 && $dayOfMonth <= 15) {
            $startDate = $now->copy()->subMonth()->startOfMonth()->addDays(25)->format("Y-m-d"); //26
            $endDate = $now->copy()->startOfMonth()->addDays(9)->format("Y-m-d"); //10
        } else {
            $startDate = $now->copy()->startOfMonth()->startOfMonth()->addDays(10)->format("Y-m-d"); //11
            $endDate = $now->copy()->startOfMonth()->addDays(24)->format("Y-m-d"); //25
        }

        foreach ($branches as $branch) {
            $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])->timeout(60)
            ->retry(3, 5000)
            ->get($scheduleLink,[
                "start_date" => $startDate,
                "end_date" => $endDate,
                "branch_short_name" => $branch->short_name,
                "company" => env("COMPANY_NAME")
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $schedules = $data['data']['schedules'];

                try {
                    DB::beginTransaction();
                    foreach ($schedules as $schedule) {
                        $employee = Employee::where("employee_number", $schedule["employee_number"])
                        ->where('status', 'active')
                        ->where("type", "Direct")
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
}
