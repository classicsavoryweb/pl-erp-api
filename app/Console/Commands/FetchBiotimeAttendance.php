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

        $startTime = Carbon::now()->subDays(1)->startOfDay();
        $endTime = Carbon::now()->endOfDay();

        $employees = Employee::where('type', 'Direct')
        ->where('status', 'active')
        ->get();
        

        // $startTime = Carbon::parse("2026-01-12")->subDays(1)->startOfDay();
        // $endTime = Carbon::parse("2026-01-16")->endOfDay();

        $failedEmployees = [];

        foreach($employees as $employee) {
            try {
                $touchedLogIds = [];
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
                if ($response->successful()) {
                    $data = $response->json();
                    $attendanceLogs = $data['data'];
                    foreach ($attendanceLogs as $log) {
                        $parsedDate = Carbon::parse($log["punch_time"]);
                        $date = $parsedDate->toDateString();
                        $time = $parsedDate->toTimeString();
                        $branch = Branch::where("device_name", $log["terminal_sn"])->first();
                        $dateToUse = $parsedDate;
                        if (Carbon::createFromTimeString($time)->lt(Carbon::createFromTimeString('04:30:00'))) {
                            $dateToUse = $parsedDate->subDay();
                        }

                        $bio = BiotimeLog::updateOrCreate([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "date" => $dateToUse->format("Y-m-d"),
                        ]);
                        $touchedLogIds[] = $bio->id;
                        if ($bio->is_modified) {
                            continue;
                        }

                        $bio->punches()->updateOrCreate([
                            "punch_time" => $log["punch_time"],
                            "date" => $dateToUse->format("Y-m-d"),
                            "branch_id" => $branch?->id ?? null
                        ]);
                    }
                    $this->updateBiotimeLogStatuses($touchedLogIds);
                }
            } catch (Exception $e) {
                $failedEmployees[] = $employee;
                Log::info($e->getMessage());
            }
        }

        // retry failed fetches
        foreach($failedEmployees as $employee) {
            try {
                $touchedLogIds = [];
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

                if ($response->successful()) {
                    $data = $response->json();
                    $attendanceLogs = $data['data'];

                    foreach ($attendanceLogs as $log) {
                        $parsedDate = Carbon::parse($log["punch_time"]);
                        $date = $parsedDate->toDateString();
                        $time = $parsedDate->toTimeString();
                        $branch = Branch::where("device_name", $log["terminal_sn"])->first();

                        $dateToUse = $parsedDate;
                        if (Carbon::createFromTimeString($time)->lt(Carbon::createFromTimeString('04:00:00'))) {
                            $dateToUse = $parsedDate->subDay();
                        }

                        $bio = BiotimeLog::updateOrCreate([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "date" => $dateToUse->format("Y-m-d"),
                        ]);
                        $touchedLogIds[] = $bio->id;
                        if ($bio->is_modified) {
                            continue;
                        }

                        $bio->punches()->updateOrCreate([
                            "punch_time" => $log["punch_time"],
                            "date" => $dateToUse->format("Y-m-d"),
                            "branch_id" => $branch?->id ?? null
                        ]);
                    }
                    $this->updateBiotimeLogStatuses($touchedLogIds);
                }
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
        }
    }

    private function updateBiotimeLogStatuses(array $logIds): void
    {
        if (empty($logIds)) {
            return;
        }

        $uniqueIds = array_values(array_unique($logIds));
        BiotimeLog::query()
            ->whereIn("id", $uniqueIds)
            ->withCount("punches")
            ->get()
            ->each(function (BiotimeLog $log) {
                $log->updateStatusFromPunchesCount((int) $log->punches_count);
            });
    }
}
