<?php

namespace App\Jobs;

use App\Mail\SendTransactionToEmployeeMail;
use App\Models\ConverterMain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTransactionToEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $converterBatch;
    /**
     * Create a new job instance.
     */
    public function __construct(ConverterMain $batch)
    {
        $this->converterBatch = $batch;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batch = $this->converterBatch;
        $converter_details = $batch->converter_details()
        ->with(["employee", "employee.latest_bank_details","transaction_type"])
        ->get();

        foreach ($converter_details as $transaction) {
            $employee = $transaction->employee;

            Mail::mailer("PAYROLL_MAILER")->to($employee->email)
            ->send(new SendTransactionToEmployeeMail($transaction));
        }
    }
}
