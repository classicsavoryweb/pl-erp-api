<?php

namespace App\Jobs;

use App\Models\BiotimeLog;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\Employee;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Log;

class FetchBranchLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $branchId;
    public function __construct($branchId)
    {
        $this->branchId = $branchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url = env('BIOMETRICS_ATTENDANCE_LINK');
        $user = env('BIOMETRICS_USERNAME');
        $password = env('BIOMETRICS_PASSWORD');
        $branchId = $this->branchId;

        $employees = Employee::where("status", "active")
        ->where("type", "Direct")
        ->where("branch_id", $branchId)
        ->get();

        $activeCutOff = Cutoff::where('status', 'active')->first();
        $startTime = Carbon::parse($activeCutOff->start_date);
        $endTime = Carbon::parse($activeCutOff->end_date);

        $failedEmployees = [];

        foreach($employees as $employee) {
            try {
                $response = Http::withBasicAuth($user, $password)
                ->withHeaders([
                    "Accept" => "application/json",
                    "Content-Type" => "application/json"
                ])
                ->timeout(60)
                ->retry(3)
                ->get($url,[
                    "start_time" => $startTime->format('Y-m-d H:i:s'),
                    "end_time" => $endTime->format('Y-m-d H:i:s'),
                    // "start_time" => "2025-08-25 00:00:00",
                    // "end_time" => "2025-09-10 23:59:59",
                    "emp_code" => $employee->biometric_number,
                    "page_size" => 10000000000
                ]);
                Log::info("CURRENT: ".$employee->full_name);
                if ($response->successful()) {
                    $data = $response->json();
                    $attendanceLogs = $data['data'];
                    Log::info("LOGGING: ".$employee->full_name);
                    foreach ($attendanceLogs as $log) {
                        $parsedDate = Carbon::parse($log["punch_time"]);
                        $date = $parsedDate->toDateString();
                        $time = $parsedDate->toTimeString();
                        $branch = Branch::where("device_name", $log["terminal_sn"])->first();

                        if (Carbon::createFromTimeString($time)->lt(Carbon::createFromTimeString('04:00:00'))) {
                            $latest = BiotimeLog::where("employee_id", $employee->id)
                            ->orderBy("date", "DESC")
                            ->where("date", '<=', $date)
                            ->where("date", '>=', $startTime->subDay()->format("Y-m-d"))
                            ->first();

                            if ($latest) {
                                $latest->punches()->updateOrCreate([
                                    "punch_time" => $log["punch_time"],
                                    "date" => $date,
                                    "branch_id" => $branch?->id ?? null
                                ]);
                            } else {
                                $bio1 = BiotimeLog::where("employee_id", $employee->id)
                                ->where("date", $date)
                                ->first();

                                if ($bio1) {
                                    $punch = $bio1->punches()
                                    ->whereTime("punch_time", $time)
                                    ->Where("date", $date)
                                    ->first();
                                    if (!$punch) {
                                        $bio1->punches()->updateOrCreate([
                                            "punch_time" => $log["punch_time"],
                                            "date" => $date,
                                            "branch_id" => $branch?->id ?? null
                                        ]);
                                    }
                                } else {
                                    $bio = BiotimeLog::updateOrCreate([
                                        "employee_id" => $employee->id,
                                        "biometric_number" => $employee->biometric_number,
                                        "date" => $date,
                                    ]);

                                    $bio->punches()->updateOrCreate([
                                        "punch_time" => $log["punch_time"],
                                        "date" => $date,
                                        "branch_id" => $branch?->id ?? null
                                    ]);
                                }
                            }

                        } else {
                            $attendance = BiotimeLog::where("employee_id", $employee->id)
                            ->where("date", $date)
                            ->first();

                            if ($attendance) {
                                $punch = $attendance->punches()
                                ->whereTime("punch_time", $time)
                                ->Where("date", $date)
                                ->first();
    
                                if (!$punch) {
                                    $attendance->punches()->updateOrCreate([
                                        "punch_time" => $log["punch_time"],
                                        "date" => $date,
                                        "branch_id" => $branch?->id ?? null
                                    ]);
                                }
                            } else {
                                $bio = BiotimeLog::updateOrCreate([
                                    "employee_id" => $employee->id,
                                    "biometric_number" => $employee->biometric_number,
                                    "date" => $date,
                                ]);
                                $bio->punches()->updateOrCreate([
                                    "punch_time" => $log["punch_time"],
                                    "date" => $date,
                                    "branch_id" => $branch?->id ?? null
                                ]);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $failedEmployees[] = $employee;
                Log::info($e->getMessage());
            }
        }

        // retry failed fetches
        foreach($failedEmployees as $employee) {
            try {
                $response = Http::withBasicAuth($user, $password)
                ->withHeaders([
                    "Accept" => "application/json",
                    "Content-Type" => "application/json"
                ])
                ->timeout(60)
                ->retry(3)
                ->get($url,[
                    "start_time" => $startTime->format('Y-m-d H:i:s'),
                    "end_time" => $endTime->format('Y-m-d H:i:s'),
                    // "start_time" => "2025-08-25 00:00:00",
                    // "end_time" => "2025-09-10 23:59:59",
                    "emp_code" => $employee->biometric_number,
                    "page_size" => 10000000000
                ]);
                Log::info("RETRY CURRENT: ".$employee->full_name);
                if ($response->successful()) {
                    $data = $response->json();
                    $attendanceLogs = $data['data'];
                    Log::info("RETRY LOGGING: ".$employee->full_name);
                    foreach ($attendanceLogs as $log) {
                        $parsedDate = Carbon::parse($log["punch_time"]);
                        $date = $parsedDate->toDateString();
                        $time = $parsedDate->toTimeString();
                        $branch = Branch::where("device_name", $log["terminal_sn"])->first();

                        if (Carbon::createFromTimeString($time)->lt(Carbon::createFromTimeString('04:00:00'))) {
                            $latest = BiotimeLog::where("employee_id", $employee->id)
                            ->orderBy("date", "DESC")
                            ->where("date", '<=', $date)
                            ->where("date", '>=', $startTime->subDay()->format("Y-m-d"))
                            ->first();

                            if ($latest) {
                                $latest->punches()->updateOrCreate([
                                    "punch_time" => $log["punch_time"],
                                    "date" => $date,
                                    "branch_id" => $branch ? $branch->id : null
                                ]);
                            } else {
                                $bio1 = BiotimeLog::where("employee_id", $employee->id)
                                ->where("date", $date)
                                ->first();

                                if ($bio1) {
                                    $punch = $bio1->punches()
                                    ->whereTime("punch_time", $time)
                                    ->Where("date", $date)
                                    ->first();

                                    if (!$punch) {
                                        $bio1->punches()->updateOrCreate([
                                            "punch_time" => $log["punch_time"],
                                            "date" => $date,
                                            "branch_id" => $branch ? $branch->id : null
                                        ]);
                                    }
                                } else {
                                    $bio = BiotimeLog::updateOrCreate([
                                        "employee_id" => $employee->id,
                                        "biometric_number" => $employee->biometric_number,
                                        "date" => $date,
                                    ]);

                                    $bio->punches()->updateOrCreate([
                                        "punch_time" => $log["punch_time"],
                                        "date" => $date,
                                        "branch_id" => $branch ? $branch->id : null
                                    ]);
                                }
                            }

                        } else {
                            $attendance = BiotimeLog::where("employee_id", $employee->id)
                            ->where("date", $date)
                            ->first();

                            if ($attendance) {
                                $punch = $attendance->punches()
                                ->whereTime("punch_time", $time)
                                ->Where("date", $date)
                                ->first();
    
                                if (!$punch) {
                                    $attendance->punches()->updateOrCreate([
                                        "punch_time" => $log["punch_time"],
                                        "date" => $date,
                                        "branch_id" => $branch ? $branch->id : null
                                    ]);
                                }
                            } else {
                                $bio = BiotimeLog::updateOrCreate([
                                    "employee_id" => $employee->id,
                                    "biometric_number" => $employee->biometric_number,
                                    "date" => $date,
                                ]);
                                $bio->punches()->updateOrCreate([
                                    "punch_time" => $log["punch_time"],
                                    "date" => $date,
                                    "branch_id" => $branch ? $branch->id : null
                                ]);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
        }
    }
}
