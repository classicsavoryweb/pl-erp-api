<?php

namespace App\Jobs;

use App\Mail\SendTransactionToEmployeeMail;
use App\Models\Accounting\Account;
use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Accounting\Vendor;
use App\Models\Branch;
use App\Models\ConverterMain;
use App\Models\ConverterDetail;
use App\Models\Deduction;
use App\Models\EmployeeDeduction;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SendTransactionToEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $converterBatch;
    protected $userId;
    protected $details;
    protected $isResend;
    /**
     * Create a new job instance.
     */

    public function __construct(ConverterMain $batch, $userId, $details = [], $isResend = false)
    {
        $this->converterBatch = $batch;
        $this->userId = $userId;
        $this->details = $details ?? [];
        $this->isResend = $isResend;
    }

    private function generateEntryId(&$counterEntry)
    {
        if ($counterEntry === null) {
            $lastEntry = Entry::orderBy('entry_id', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->entry_id, 3);
                $counterEntry = $lastEntryIdNumber + 1;
            } else {
                $counterEntry = 1; 
            }
        }

        return 'PSD' . str_pad($counterEntry++, 8, '0', STR_PAD_LEFT);
    }

    private function generateApsysEntryId(&$counterApsysEntry)
    {
        if ($counterApsysEntry === null) {
            $lastEntry = ApsysEntry::orderBy('ent_tran_no', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->ent_tran_no, 3);
                $counterApsysEntry = $lastEntryIdNumber + 1;
            } else {
                $counterApsysEntry = 1; 
            }
        }

        return 'PSD' . str_pad($counterApsysEntry++, 8, '0', STR_PAD_LEFT);
    }

    private function generateApsysEntryMainId(&$counterApsysEntryMain)
    {
        if ($counterApsysEntryMain === null) {
            $lastEntry = ApsysEntryMain::orderBy('ent_tran_no', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->ent_tran_no, 3);
                $counterApsysEntryMain = $lastEntryIdNumber + 1;
            } else {
                $counterApsysEntryMain = 1; 
            }
        }

        return 'PSD' . str_pad($counterApsysEntryMain++, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batch = $this->converterBatch;

        $transactionsIds = $this->details;

        $userId = $this->userId;
        $user = User::find($userId);
        $userId = $user->employee->id;

        $converter_details = $batch->converter_details()
        ->with(["employee", "employee.latest_bank_details","transaction_type"]);

        if (count($transactionsIds) > 0) {
            $converter_details = $converter_details->whereIn("id", $transactionsIds);
        }

        $converter_details = $converter_details->get();

        $counterEntry = null;
        $counterApsysEntry = null;
        $counterApsysEntryMain = null;

        foreach ($converter_details as $transaction) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);

            $employee = $transaction->employee;
            $employeeNumber = $transaction->employee->employee_number;

            $vendorHO = Vendor::where("ven_name", "HO")->first();
            $vendorHONo = $vendorHO->ven_no;
            $vendorHOName = $vendorHO->ven_name;

            $branchHO = Branch::where('short_name', 'HO')
            ->first();
            $branchHOId = $branchHO->id;
            $branchHOName = $branchHO->name;
            $branchHONumber = $branchHO->branch_number;

            $batchNumber = $transaction->converter_main->batch_number;
            $amount = $transaction->amount;
            $description = $transaction->transaction_type->description;
            $isBlockPayment = $transaction->transaction_type->is_block_payment;
            $hoDebit = $transaction->transaction_type->ho_debit;
            $hoCredit = $transaction->transaction_type->ho_credit;
            $branchDebit = $transaction->transaction_type->branch_debit;
            $branchCredit = $transaction->transaction_type->branch_credit;
            $advancesDebit = $transaction->transaction_type->advance_debit;
            $branchNumber = $transaction->branch->branch_number;
            $branchShortName = $transaction->branch->short_name;
            $branchName = $transaction->branch->name;
            $branchId = $transaction->branch_id;

            //HO Entry Setup
            if ($hoDebit) {

                $accountHODebitId = null;

                if ($advancesDebit == 'Yes') {
                    $accountHODebitId = Account::where('acc_name', 'Advances - ' . $branchShortName)
                    ->value('acc_no');
                }

                $hoEntries = [
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchHOName,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $amount,
                        'acc_no'              => $accountHODebitId ?? $hoDebit,
                        'description_per_acc' => $description,
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => $isBlockPayment,
                    ],
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchHOName,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $amount,
                        'acc_no'              => $hoCredit,
                        'description_per_acc' => $description,
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => $isBlockPayment,
                    ]
                ];

                if (!$this->isResend) {
                    $this->createEntries($hoEntries, $userId);
                }
            }

            //Branch Entry Setup
            if ($branchDebit) {
                $branchEntries = [
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchName,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $amount,
                        'acc_no'              => $branchDebit,
                        'description_per_acc' => $description,
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => $isBlockPayment,
                    ],
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchName,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $amount,
                        'acc_no'              => $branchCredit,
                        'description_per_acc' => $description,
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => $isBlockPayment,
                    ],
                ];

                if (!$this->isResend) {
                    $this->createEntries($branchEntries, $userId);
                }
            }

            Mail::mailer("WEEKLY_PAYROLL_MAILER")->to($employee->email)
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

    private function createEntries($entries, $userId)
    {
        foreach ($entries as $entry) {

            Entry::create([
                'entry_id'                   => $entry['entry_id'],
                'document_no'                => $entry['document_no'],
                'branch_id'                  => $entry['branch_id'],
                'branch_number'              => $entry['branch_number'],
                'vendor_no'                  => $entry['ven_no'],
                'amount'                     => $entry['amount'],
                'account_no'                 => $entry['acc_no'],
                'description'                => $entry['description_per_acc'],
                'balance_type'               => $entry['balance_type'],
                'is_block_payment'           => $entry['is_block_payment'],
                'status'                     => 'Unapplied',
                'generated_at'               => now(),
                'generated_by'               => $userId,
            ]);

            ApsysEntry::create([
                'ent_tran_no'       => $entry['apsys_entry_id'],
                'branch_id'         => $entry['branch_number'],
                'ent_post_date'     => now(),
                'ven_no'            => $entry['ven_no'],
                'ent_doc_no'        => $entry['document_no'],
                'ent_doc_date'      => now(),
                'ent_due_date'      => now(),
                'ent_header'        => $entry['document_no'],
                'ent_crf_no'        => '',
                'ent_trans_amt'     => $entry['amount'],
                'ent_amt_due'       => $entry['amount'],
                'ent_module'        => 'Payroll',
                'acc_no'            => $entry['acc_no'],
                'ent_amt_per_cc'    => $entry['amount'],
                'ent_desc_per_acc'  => $entry['description_per_acc'],
                'ent_norm_bal'      => $entry['balance_type'],
                'ent_total'         => $entry['amount'],
                'ent_module_type'   => 'Single',
                'ent_app_amt'       => $entry['amount'],
                'ent_unapp_amt'     => 0,
                'ent_vat_amt'       => 0,
                'ent_nonvat_amt'    => 0,
                'ent_na_amt'        => $entry['amount'],
                'ent_inp_tax_amt'   => 0,
                'ent_block_payment' => ($entry['is_block_payment'] == 1) ? 'Yes' : 'No',
                'ent_status'        => 'Unapplied',
                'ent_chk'           => 'No',
                'date_added'        => now(),
            ]);

            ApsysEntryMain::create([
                'ent_tran_no'       => $entry['apsys_entry_main_id'],
                'ven_no'            => $entry['ven_no'],
                'ven_name'          => $entry['ven_name'],
                'ent_doc_date'      => now(),
                'branch_id'         => $entry['branch_number'],
                'branch_name'         => $entry['branch_name'],
                'ent_header'        => $entry['document_no'],
                'ent_trans_amt'     => $entry['amount'],
                'ent_post_date'     => now(),
                'ent_module_type'   => 'Single',
                'ent_status'        => 'Unapplied',
                'check_no'          => null,
                'ent_crf_no'        => '',
                'ent_doc_no'        => $entry['document_no'],
                'ent_module'        => 'Payroll',
                'ent_app_amt'       => $entry['amount'],
                'ent_unapp_amt'     => 0,
                'ent_block_payment' => ($entry['is_block_payment'] == 1) ? 'Yes' : 'No',
                'ent_due_date'      => now(),
            ]);
        }
    }
}
