<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Overtime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchOvertimeInCSHub extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-overtime-in-cs-hub';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching applied overtime in cshub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $overtimeLink = env('CS_HUB_URL_LINK')."/overtime";
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');

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
            "start_date" => $startDate,
            "end_date" => $endDate,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $overtimes = $data['data']['overtime'];

            foreach($overtimes as $overtime) {

                $employee = Employee::where("biometric_number", $overtime["user"]["employee_biometric"])->first();

                if ($employee) {
                    Log::info($employee->full_name." - ".$overtime["total_hrs"]);
                    $isExisting = Overtime::where("employee_id", $employee->id)
                    ->where("overtime_date",$overtime["overtime_date"])
                    ->first();
                    if ($isExisting && $isExisting->status === "Unpaid") {
                        $isExisting->delete();
                    }

                    Overtime::create([
                        "cshub_overtime_id" => $overtime["id"],
                        "date_filed" => $overtime["date_filed"],
                        "employee_id"=> $employee->id,
                        "biometric_number" => $employee->biometric_number,
                        "overtime_date" => $overtime["overtime_date"],
                        "purpose" => $overtime["purpose"],
                        "total_hours" => $overtime["total_hrs"],
                        "status" => ucfirst($overtime["status_payroll"]),
                    ]);
                }
            }
        }
        
    }
}
