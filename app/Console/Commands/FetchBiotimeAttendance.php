<?php

namespace App\Console\Commands;

use App\Models\BiotimeLog;
use App\Models\Branch;
use App\Models\Employee;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Log;
use DB;

class FetchBiotimeAttendance extends Command
{
    protected $signature = 'app:fetch-biotime-attendance';

    protected $description = 'Fetch the attendance in the biotime device';

    public function handle()
    {
        $url = env('BIOMETRICS_ATTENDANCE_LINK');
        $user = env('BIOMETRICS_USERNAME');
        $password = env('BIOMETRICS_PASSWORD');
        $employees = Employee::where("status", "active")
        ->where("type", "Direct")
        ->get();

        $now = Carbon::now();
        $startTime = $now->copy()
        ->startOfMonth()
        ->addDays(10)
        ->startOfDay();
        $endTime = $now->copy()
        ->startOfMonth()
        ->addDays(24)
        ->endOfDay();
        $dayOfMonth = Carbon::now()->day;

        if ($dayOfMonth >= 1 && $dayOfMonth <= 15) {
            $startTime = $now->copy()->subMonth()->startOfMonth()->addDays(25)->startOfDay(); //26
            $endTime = $now->copy()->startOfMonth()->addDays(9)->endOfDay(); //10
        } else {
            $startTime = $now->copy()->startOfMonth()->startOfMonth()->addDays(10)->startOfDay(); //11
            $endTime = $now->copy()->startOfMonth()->addDays(24)->endOfDay(); //25
        }

        $failedEmployees = [];

        foreach($employees as $employee) {
            try {
                $response = Http::withBasicAuth($user, $password)
                ->withHeaders([
                    "Accept" => "application/json",
                    "Content-Type" => "application/json"
                ])
                ->timeout(60)
                ->get($url,[
                    // "start_time" => $startTime->format('Y-m-d H:i:s'),
                    // "end_time" => $endTime->format('Y-m-d H:i:s'),
                    "start_time" => "2025-06-10 00:00:00",
                    "end_time" => "2025-06-30 23:59:59",
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
                        if (Carbon::createFromTimeString($time)->lt(Carbon::createFromTimeString('05:00:00'))) {
                            $latest = BiotimeLog::where("employee_id", $employee->id)->orderBy("date", "DESC")->first();
                            if ($latest) {
                                $latest->punches()->create([
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
                                        $bio1->punches()->create([
                                            "punch_time" => $log["punch_time"],
                                            "date" => $date,
                                            "branch_id" => $branch?->id ?? null
                                        ]);
                                    }
                                } else {
                                    $bio = BiotimeLog::create([
                                        "employee_id" => $employee->id,
                                        "biometric_number" => $employee->biometric_number,
                                        "date" => $date,
                                    ]);

                                    $bio->punches()->create([
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
                                    $attendance->punches()->create([
                                        "punch_time" => $log["punch_time"],
                                        "date" => $date,
                                        "branch_id" => $branch?->id ?? null
                                    ]);
                                }
                            } else {
                                $bio = BiotimeLog::create([
                                    "employee_id" => $employee->id,
                                    "biometric_number" => $employee->biometric_number,
                                    "date" => $date,
                                ]);
                                $bio->punches()->create([
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
                ->get($url,[
                    // "start_time" => $startTime->format('Y-m-d H:i:s'),
                    // "end_time" => $endTime->format('Y-m-d H:i:s'),
                    "start_time" => "2025-06-10 00:00:00",
                    "end_time" => "2025-06-30 23:59:59",
                    "emp_code" => $employee->biometric_number,
                    "page_size" => 10000000000
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $attendanceLogs = $data['data'];

                    foreach ($attendanceLogs as $log) {
                        $parsedDate = Carbon::parse($log["punch_time"]);
                        $date = $parsedDate->toDateString();
                        $time = $parsedDate->toTimeString();
                        $branch = Branch::where("device_name", $log["terminal_sn"])->first();

                        if (Carbon::createFromTimeString($time)->lt(Carbon::createFromTimeString('04:00:00'))) {
                            $latest = BiotimeLog::where("employee_id", $employee->id)->orderBy("date", "DESC")->first();
                            if ($latest) {
                                $latest->punches()->create([
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
                                        $bio1->punches()->create([
                                            "punch_time" => $log["punch_time"],
                                            "date" => $date,
                                            "branch_id" => $branch ? $branch->id : null
                                        ]);
                                    }
                                } else {
                                    $bio = BiotimeLog::create([
                                        "employee_id" => $employee->id,
                                        "biometric_number" => $employee->biometric_number,
                                        "date" => $date,
                                    ]);

                                    $bio->punches()->create([
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
                                    $attendance->punches()->create([
                                        "punch_time" => $log["punch_time"],
                                        "date" => $date,
                                        "branch_id" => $branch ? $branch->id : null
                                    ]);
                                }
                            } else {
                                $bio = BiotimeLog::create([
                                    "employee_id" => $employee->id,
                                    "biometric_number" => $employee->biometric_number,
                                    "date" => $date,
                                ]);
                                $bio->punches()->create([
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
