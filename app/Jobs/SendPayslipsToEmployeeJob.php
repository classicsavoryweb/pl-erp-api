<?php

namespace App\Jobs;

use App\Mail\SendPayslipEmail;
use App\Models\Cutoff;
use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use App\Support\NotifiesJobStatus;
use Exception;
use Log;

class SendPayslipsToEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    protected $payrolls;
    protected $userId;
    public function __construct(Collection $payrolls, ?int $userId = null)
    {
        $this->payrolls = $payrolls ?? [];
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
        $payslips = $this->payrolls;

        foreach ($payslips as $payslip) {
            try {
                $employee = $payslip->employee;
                $cutOff = Cutoff::where("pay_date", $payslip->pay_date)->first();
                $emails = explode('/', $employee->email);
                Mail::mailer("PAYROLL_MAILER")->to($emails)
                    ->send(new SendPayslipEmail($payslip, $cutOff));
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
}
