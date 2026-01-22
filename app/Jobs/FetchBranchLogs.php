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
use App\Support\NotifiesJobStatus;
use Log;

class FetchBranchLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    protected $branchId;
    protected $userId;
    protected $notificationContext;
    public function __construct($branchId, ?int $userId = null, ?string $notificationContext = null)
    {
        $this->branchId = $branchId;
        $this->userId = $userId;
        $this->notificationContext = $notificationContext;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $url = env('BIOMETRICS_ATTENDANCE_LINK');
            $user = env('BIOMETRICS_USERNAME');
            $password = env('BIOMETRICS_PASSWORD');
            $branchId = $this->branchId;

            $employees = Employee::where("status", "active")
            ->where("type", "Direct")
            ->where("branch_id", $branchId)
            ->get();

            $activeCutOff = Cutoff::where('status', 'active')->first();
            $startTime = Carbon::parse($activeCutOff->start_date)->startOfDay();
            $endTime = Carbon::parse($activeCutOff->end_date)->endOfDay();

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
                            if ($bio->is_modified) {
                                continue;
                            }

                            $bio->punches()->updateOrCreate([
                                "punch_time" => $log["punch_time"],
                                "date" => $dateToUse->format("Y-m-d"),
                                "branch_id" => $branch?->id ?? null
                            ]);
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
                            if ($bio->is_modified) {
                                continue;
                            }

                            $bio->punches()->updateOrCreate([
                                "punch_time" => $log["punch_time"],
                                "date" => $dateToUse->format("Y-m-d"),
                                "branch_id" => $branch?->id ?? null
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    Log::info($e->getMessage());
                }
            }

            $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }

    public function getNotificationLabel(): string
    {
        $label = "Fetch Actual Schedule";
        return $this->notificationContext ? $label." ".$this->notificationContext : $label;
    }
}
