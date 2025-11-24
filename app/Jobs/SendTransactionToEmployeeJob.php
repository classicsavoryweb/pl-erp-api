<?php

namespace App\Jobs;

use App\Mail\SendTransactionToEmployeeMail;
use App\Models\ConverterMain;
use App\Models\EmployeeDeduction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTransactionToEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $converterBatch;
    protected $details;
    /**
     * Create a new job instance.
     */
    public function __construct(ConverterMain $batch, $details = [])
    {
        $this->converterBatch = $batch;
        $this->details = $details ?? [];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batch = $this->converterBatch;
        $transactionsIds = $this->details;
        $converter_details = $batch->converter_details()
        ->with(["employee", "employee.latest_bank_details","transaction_type"]);

        if (count($transactionsIds) > 0) {
            $converter_details = $converter_details->whereIn("id", $transactionsIds);
        }

        $converter_details = $converter_details->get();

        foreach ($converter_details as $transaction) {
            $employee = $transaction->employee;

            Mail::mailer("PAYROLL_MAILER")->to($employee->email)
            ->send(new SendTransactionToEmployeeMail($transaction));

            if ($transaction->transaction_type->isDeduction()) {
                $monthlyAmortization = $transaction->amount / $transaction->terms;
                $deduction = $transaction->transaction_type->deduction;

                $isExisting = EmployeeDeduction::where("employee_id", $employee->id)
                ->where("deduction_id", $transaction->transaction_type->deduction_id)
                ->where("amount", $transaction->amount)
                ->first();

                if (!$isExisting) {
                    EmployeeDeduction::create([
                        "employee_id" => $employee->id,
                        "deduction_id" => $transaction->transaction_type->deduction_id,
                        "months" => $transaction->terms,
                        "amount" => $transaction->amount,
                        "source_of_fund" => "HO",
                        "start_date" => now()->format("Y-m-d"),
                        "end_date" => now()->addMonths($transaction->terms)->format("Y-m-d"),
                        "monthly_amortization" => round($monthlyAmortization, 2),
                        "balance" => $transaction->amount,
                        "remarks" => $transaction->remarks,
                        "debit_id" => $deduction->debit_id,
                        "credit_id" => $deduction->credit_id,
                        "branch_id" => $employee->current_branch->id,
                        "block_payment" => "Yes",
                        "approved_by" => $batch->generated_by,
                        "approved_date" => $batch->generated_at,
                        "approved_status" => "Yes",
                        // "deduction_ref" => $deduction_reference,
                        // "deduction_status" => $request->deduction_status,
                        // "created_by" => Auth::user()->full_name,
                        "date_created" => $transaction->created_at->format("Y-m-d"),
                    ]);
                }
            }            
        }
    }
}
