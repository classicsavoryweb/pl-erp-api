<?php

namespace App\Jobs;

use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Tardiness;
use App\Models\TardinessMinutes;
use App\Models\TardinessSanction;
use App\Models\TimeKeeping;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\NotifiesJobStatus;

class ComputeEmployeeTardiness implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    protected $endDate;

    public function __construct($endDate)
    {
        $this->$endDate = $endDate;
    }

    public function handle(): void
    {
        try {
            $endDate = $this->endDate;
            $startDate = Carbon::parse($endDate)->subMonth()->addDay()->format("Y-m-d");
            $year = Carbon::parse($startDate)->year;
            $month = Carbon::parse($startDate)->month;
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

                if ($totalTardy > 60) {
                    $latestTransgression = Tardiness::with(["tardiness_sanction"])
                    ->where("employee", $employee->id)
                    ->where("status", "Approved")
                    ->latest();

                    $sanction = TardinessSanction::where("rank", 1)->first();

                    if ($latestTransgression) {
                        $oldSanction = $latestTransgression->tardiness_sanction;

                        $sanction = TardinessSanction::where("rank", $oldSanction+1)->first();
                    }

                    $tardy = Tardiness::create([
                        'employee_id' => $employee->id,
                        "branch_id" => $employee->current_branch->id,
                        "month" => $month,
                        "year" => $year,
                        "total_minutes" => $totalTardy,
                        "tardiness_sanction_id" => $sanction->id,
                        "offense_punishment" => $sanction->punishment,
                        "status" => "Pending",
                    ]);

                    foreach ($timeKeepings as $timeKeeping) {
                        TardinessMinutes::create([
                            "employee_id" => $employee->id,
                            "late_date" => $timeKeeping->date,
                            "late_minutes" => $timeKeeping->tardiness,
                            "month" => $month,
                            "year" => $year
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
