<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Overtime;
use Carbon\Carbon;
use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Support\NotifiesJobStatus;

class FetchOvertimeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    protected $branchId;
    protected $userId;
    protected $notificationContext;

    public function __construct(?int $branchId = null, ?int $userId = null, ?string $notificationContext = null)
    {
        $this->branchId = $branchId;
        $this->userId = $userId;
        $this->notificationContext = $notificationContext;
    }
    public function handle(): void
    {
        try {
            $overtimeLink = env('CS_HUB_URL_LINK')."/overtime";
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');
            $branchId = $this->branchId;
            if (!$branchId) {
                Log::warning("FetchOvertimeJob skipped: missing branch id.");
                $this->notifyJobFinished();
                return;
            }

            $branch = Branch::find($branchId);
            if (!$branch) {
                Log::warning("FetchOvertimeJob skipped: branch not found for id ".$branchId);
                $this->notifyJobFinished();
                return;
            }
            $activeCutOff = Cutoff::where("status", "active")->first();
            
            $now = Carbon::now();
            $start_date = $now->copy()
            ->startOfMonth()
            ->addDays(10)
            ->startOfDay();
            $end_date = $now->copy()
            ->startOfMonth()
            ->addDays(24)
            ->endOfDay();
            $dayOfMonth = Carbon::now()->day;

            if ($dayOfMonth >= 1 && $dayOfMonth <= 15) {
                $start_date = $now->copy()->subMonth()->startOfMonth()->addDays(25)->format("Y-m-d"); //26
                $end_date = $now->copy()->startOfMonth()->addDays(9)->format("Y-m-d"); //10
            } else {
                $start_date = $now->copy()->startOfMonth()->startOfMonth()->addDays(10)->format("Y-m-d"); //11
                $end_date = $now->copy()->startOfMonth()->addDays(24)->format("Y-m-d"); //25
            }

            if ($activeCutOff) {
                $start_date = $activeCutOff->start_date;
                $end_date = $activeCutOff->end_date;
            }

            $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])->timeout(60)
            ->retry(50, 1000, function ($exception, $attempt) {
                return $exception instanceof ConnectionException ||
                    str_contains($exception->getMessage(), 'cURL error 60');
            })
            ->get($overtimeLink,[
                "start_date" => $start_date,
                "end_date" => $end_date,
                "branch_number" => $branch->branch_number
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $overtimes = $data['data']['overtime'];


                foreach($overtimes as $overtime) {
                    $employee = Employee::where("biometric_number", $overtime["user"]["employee_biometric"])->first();

                    if ($employee) {
                        $isExisting = Overtime::where("employee_id", $employee->id)
                        ->where("overtime_date",$overtime["overtime_date"])
                        ->first();
                        if ($isExisting && $isExisting->status === "Unpaid") {
                            $isExisting->delete();
                        }

                        Overtime::updateOrCreate([
                            "date_filed" => $overtime["date_filed"],
                            "employee_id"=> $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "overtime_date" => $overtime["overtime_date"],
                        ],[
                            "cshub_overtime_id" => $overtime["id"],
                            "purpose" => $overtime["purpose"],
                            "total_hours" => $overtime["total_hrs"],
                            "status" => ucfirst($overtime["status_payroll"]),
                        ]);
                    }
                }
            }

            $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
