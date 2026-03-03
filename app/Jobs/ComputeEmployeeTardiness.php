<?php

namespace App\Jobs;

use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Tardiness;
use App\Models\TardinessMinutes;
use App\Models\TardinessSanction;
use App\Models\TimeKeeping;
use Carbon\Carbon;
use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\NotifiesJobStatus;
use Exception;
use Log;

class ComputeEmployeeTardiness implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    protected $endDate;

    public function __construct($endDate)
    {
        $this->endDate = $endDate;
    }

    public function handle(): void
    {
        try {
            $baseUrl = env('CS_HUB_URL_LINK');
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');

            $endDate = $this->endDate;
            $startDate = Carbon::parse($endDate)->subMonth()->day(26)->format("Y-m-d");
            $year = Carbon::parse($endDate)->year;
            $month = Carbon::parse($endDate)->month;
            $employees = Employee::where("status", "active")->get();

            foreach ($employees as $employee) {
                $timeKeepings = TimeKeeping::whereBetween("date", [$startDate, $endDate])
                ->where("employee_id", $employee->id)
                ->where("tardiness", ">", 0)
                ->get();

                $totalTardy = 0;

                foreach ($timeKeepings as $timeKeeping) {
                    $totalTardy += $timeKeeping->tardiness;
                }

                if ($totalTardy >= 60) {
                    $endpoint = $baseUrl."/get-applied-tardiness";
                    try {
                        $response = Http::withBasicAuth($user, $password)
                        ->withHeaders([
                            "Accept" => "application/json",
                            "Content-Type" => "application/json",
                        ])
                        ->timeout(30)
                        ->get($endpoint, [
                            "employee_number" => $employee->employee_number,
                            "date_from" => $startDate,
                            "date_to" => $endDate,
                            "company" => env("COMPANY_NAME")
                        ]);

                        if ($response->successful()) {
                            $data = $response->json();
                            $excusedMinutes = $data['data']['excused_minutes'];
    
                            if ($excusedMinutes > 0) {
                                $totalTardy = $totalTardy - $excusedMinutes;
                            }
                        }
                    } catch (Exception $e) {
                        Log::info($e->getMessage());
                    }
                }

                if ($totalTardy >= 60) {
                    $latestTransgression = Tardiness::with(["tardiness_sanction"])
                    ->where("employee_id", $employee->id)
                    ->where("status", "Approved")
                    ->where("year", $year)
                    ->latest()
                    ->first();

                    $sanction = TardinessSanction::where("rank", 1)->first();

                    if ($latestTransgression) {
                        $oldSanction = $latestTransgression->tardiness_sanction;

                        $sanction = TardinessSanction::where("rank", $oldSanction->rank +1)->first();
                    }

                    $tardy = Tardiness::updateOrCreate([
                        'employee_id' => $employee->id,
                        "branch_id" => $employee->current_branch->id,
                        "month" => $month,
                        "year" => $year,
                    ],[
                        "total_minutes" => $totalTardy,
                        "tardiness_sanction_id" => $sanction->id,
                        "offense_punishment" => $sanction->punishment,
                        "status" => "Pending",
                    ]);

                    foreach ($timeKeepings as $timeKeeping) {
                        if ($timeKeeping->tardiness > 0) {
                            TardinessMinutes::updateOrCreate([
                                "employee_id" => $employee->id,
                                "late_date" => $timeKeeping->date,
                                "month" => $month,
                                "year" => $year
                            ],[
                                "late_minutes" => $timeKeeping->tardiness,
                            ]);
                        }
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
