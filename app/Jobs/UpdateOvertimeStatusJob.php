<?php

namespace App\Jobs;

use App\Models\Overtime;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateOvertimeStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $cutOff;
    /**
     * Create a new job instance.
     */
    public function __construct($cutOff = [])
    {
        $this->cutOff = $cutOff;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutOff = $this->cutOff;
        $cutOffPaid = Carbon::parse($cutOff["start_time"])->format("m/d/Y")." TO ". Carbon::parse($cutOff['end_time'])->format("m/d/Y");

        $overtimes = Overtime::where("cutoff_paid", $cutOffPaid)->get();

        foreach ($overtimes as $overtime) {
            $overtimeLink = env('CS_HUB_URL_LINK')."/update-overtime/".$overtime->cshub_overtime_id;
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');
            $payrollStatus = $overtime->hours_paid >= 1 ? "unpaid" : "paid";

            try {
                Http::withBasicAuth($user, $password)
                ->withHeaders([
                    "Accept" => "application/json",
                    "Content-Type" => "application/json"
                ])->timeout(60)
                ->post($overtimeLink,[
                    "status_payroll" => $payrollStatus,
                    "status" => "approved",
                ]);
            } catch (Exception $e) {
                // Log::info("CSHub overtime api error encountered: ".$e->getMessage());
            }
        }
        
    }
}
