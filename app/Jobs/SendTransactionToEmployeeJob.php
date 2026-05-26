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
use App\Support\EntryBranchNumber;
use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Support\NotifiesJobStatus;
use Exception;
use Log;

class SendTransactionToEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

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

    public function middleware()
    {
        return [
            (new WithoutOverlapping('accounting_psd_sequence'))
                ->shared()
                ->releaseAfter(1200)
                ->expireAfter(7200)
        ];
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

        return 'PSD' . str_pad($counterEntry++, 8, '0', STR_PAD_LEFT)."-ERP";
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

        return 'PSD' . str_pad($counterApsysEntry++, 8, '0', STR_PAD_LEFT)."-ERP";
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

        return 'PSD' . str_pad($counterApsysEntryMain++, 8, '0', STR_PAD_LEFT)."-ERP";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
        $batch = $this->converterBatch;

        $transactionsIds = $this->details;

        $userId = $this->userId;

        $userEmployee = User::find($userId);

        $converter_details = $batch->converter_details()
        ->with(["employee", "employee.latest_bank_details","transaction_type"]);

        if (count($transactionsIds) > 0) {
            $converter_details = $converter_details->whereIn("id", $transactionsIds);
        }

        $converter_details = $converter_details->get();

        $counterEntry = null;
        $counterApsysEntry = null;
        $counterApsysEntryMain = null;

        $vendorHO = Vendor::where("ven_name", "HO")->first();
        $vendorHONo = $vendorHO->ven_no;
        $vendorHOName = $vendorHO->ven_name;

        $branchHO = Branch::where('short_name', 'HO')->first();
        $branchHOId = $branchHO->id;
        $branchHOName = $branchHO->name;
        $branchHOShortName = $branchHO->short_name;
        $branchHONumber = $branchHO->branch_number;

        $batchEntryTransactionIds = [];
        $batchEntryGroups = $converter_details
            ->filter(function ($transaction) {
                return ($transaction->transaction_type->batch_entry ?? "No") === "Yes";
            })
            ->groupBy("transaction_type_id");

        foreach ($batchEntryGroups as $transactionTypeId => $transactions) {
            $firstTransaction = $transactions->first();

            if (!$firstTransaction) {
                continue;
            }

            $batchEntryTransactionIds = array_merge($batchEntryTransactionIds, $transactions->pluck("id")->all());

            $transactionType = $firstTransaction->transaction_type;
            $batchNumber = $firstTransaction->converter_main->batch_number;
            $description = $transactionType->description;
            $isBlockPayment = $transactionType->is_block_payment;
            $hoDebit = $transactionType->ho_debit;
            $hoCredit = $transactionType->ho_credit;
            $advancesDebit = $transactionType->advances_debit;
            $amount = $transactions->sum("amount");
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $documentNo = $batchNumber . ' | ' . $description . ' | ' . $branchHOName;

            if (!$hoDebit && $advancesDebit != 'Yes') {
                continue;
            }

            $accountHODebitId = null;

            if ($advancesDebit == 'Yes') {
                $accountHODebitId = Account::where('acc_name', 'Advances - ' . $branchHOShortName)
                    ->value('acc_no');
            }

            $batchEntries = [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $documentNo,
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
                    'document_no'         => $documentNo,
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
                ],
            ];

            if (!$this->isResend) {
                $this->createEntries($batchEntries, $userId, $vendorHONo, $vendorHOName);
            }
        }

        foreach ($converter_details as $transaction) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);

            $employee = $transaction->employee;
            $employeeNumber = $transaction->employee->employee_number;

            $batchNumber = $transaction->converter_main->batch_number;
            $amount = $transaction->amount;
            $description = $transaction->transaction_type->description;
            $isBlockPayment = $transaction->transaction_type->is_block_payment;
            $hoDebit = $transaction->transaction_type->ho_debit;
            $hoCredit = $transaction->transaction_type->ho_credit;
            $branchDebit = $transaction->transaction_type->branch_debit;
            $branchCredit = $transaction->transaction_type->branch_credit;
            $advancesDebit = $transaction->transaction_type->advances_debit;
            $branchNumber = $transaction->branch->branch_number;
            $branchShortName = $transaction->branch->short_name;
            $branchName = $transaction->branch->name;
            $branchId = $transaction->branch_id;
            $entryVendorNo = $employee->employee_number;
            $entryVendorName = $employee->full_name;
            $isBatchEntry = in_array($transaction->id, $batchEntryTransactionIds, true);

            $type = null;
            if (in_array($transaction->transaction_type->source_id, [1,2])) {
                $type = $transaction->transaction_type->source_id == 1 ? "LOAN" : "HEALTHCARE";
            }
            //HO Entry Setup
            if (!$isBatchEntry && ($hoDebit || $advancesDebit == 'Yes')) {

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
                        'ven_no'              => $entryVendorNo,
                        'ven_name'            => $entryVendorName,
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
                        'ven_no'              => $entryVendorNo,
                        'ven_name'            => $entryVendorName,
                        'amount'              => $amount,
                        'acc_no'              => $hoCredit,
                        'description_per_acc' => $description,
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => $isBlockPayment,
                    ]
                ];

                if (!$this->isResend) {
                    $this->createEntries($hoEntries, $userId, $entryVendorNo, $entryVendorName);
                }
            }

            $entryId2 = $this->generateEntryId($counterEntry);
            $apsysEntryId2 = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId2 = $this->generateApsysEntryMainId($counterApsysEntryMain);

            //Branch Entry Setup
            if (!$isBatchEntry && $branchDebit) {
                $branchEntries = [
                    [
                        'entry_id'            => $entryId2,
                        'apsys_entry_id'      => $apsysEntryId2,
                        'apsys_entry_main_id' => $apsysEntryMainId2,
                        'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchName,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchName,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $amount,
                        'acc_no'              => $branchDebit,
                        'description_per_acc' => $description,
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => $isBlockPayment,
                    ],
                    [
                        'entry_id'            => $entryId2,
                        'apsys_entry_id'      => $apsysEntryId2,
                        'apsys_entry_main_id' => $apsysEntryMainId2,
                        'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchName,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchName,
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
                    $this->createEntries($branchEntries, $userId, $vendorHONo, $vendorHOName);
                }
            }

            try {
                Mail::mailer("WEEKLY_PAYROLL_MAILER")->to($employee->email)
                ->send(new SendTransactionToEmployeeMail($transaction));
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
            
            if (in_array($transaction->transaction_type->source_id, [1,2])) {
                try {
                    $baseUrl = env('CS_HUB_URL_LINK');
                    $user = env('CS_HUB_ADMIN_EMAIL');
                    $password = env('CS_HUB_ADMIN_PASSWORD');
                    $endPoint = $baseUrl."/update-transaction-status";
                    $response = Http::withBasicAuth($user, $password)
                        ->withHeaders([
                            "Accept" => "application/json",
                            "Content-Type" => "application/json",
                        ])
                        ->timeout(30)
                        ->post($endPoint, [
                            "transaction_number" => $transaction->transaction_number,
                            "status" => "Credited",
                            "type" => $type,
                            "employee_number" => $userEmployee->employee_number,
                            "company" => env("COMPANY_NAME")
                        ]);          
                } catch (Exception $e) {
                    Log::info($e->getMessage());
                }
            }

            if ($transaction->transaction_type->isDeduction()) {
                $monthlyAmortization = $transaction->terms > 0 ? $transaction->amount / $transaction->terms : $transaction->amount;
                $deduction = $transaction->transaction_type->deduction;

                $deduction_count = EmployeeDeduction::where("employee_id", $employee->id)
                    ->whereNotNull("deduction_ref")
                    ->where("deduction_ref", "!=", '')
                    ->count();

                $base_reference = $employee->first_initial . $employee->middle_initial . $employee->last_initial . $employee->employee_number;
                $deductionId = str_pad(8, 8, '0', STR_PAD_LEFT);
                $deduction_reference = $base_reference . $deductionId . "|" . ($deduction_count + 1);
                $debitId = $deduction->debit_id;
                $creditId = $deduction->credit_id;
                $user = User::find($userId);

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
                    "end_loan_status" => "No",
                    "deduction_ref" => $deduction_reference,
                    "created_by" => $user ? $user->full_name : null,
                    "date_created" => $transaction->created_at->format("Y-m-d"),
                ]);

                if (!$isBatchEntry && (!$hoDebit || $advancesDebit != 'Yes')) {
                    $entryId = $this->generateEntryId($counterEntry);
                    $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
                    $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
                    $deductionEntry = [];
                    $deductionEntry = array_merge($deductionEntry, [
                        [
                            'entry_id'            => $entryId,
                            'apsys_entry_id'      => $apsysEntryId,
                            'apsys_entry_main_id' => $apsysEntryMainId,
                            'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchHOName,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'branch_name'         => $branchHOName,
                            'ven_no'              => $employee->employee_number,
                            'ven_name'            => $employee->full_name,
                            'amount'              => $transaction->amount,
                            'acc_no'              => $debitId,
                            'description_per_acc' => $deduction->description,
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $deduction->is_block_payment,
                        ],
                        [
                            'entry_id'            => $entryId,
                            'apsys_entry_id'      => $apsysEntryId,
                            'apsys_entry_main_id' => $apsysEntryMainId,
                            'document_no'         => $batchNumber . ' | ' . $employeeNumber . ' | ' . $branchHOName,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'branch_name'         => $branchHOName,
                            'ven_no'              => $employee->employee_number,
                            'ven_name'            => $employee->full_name,
                            'amount'              => $transaction->amount,
                            'acc_no'              => $creditId,
                            'description_per_acc' => $deduction->description,
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $deduction->is_block_payment,
                        ]
                    ]);
    
                    $this->createEntries($deductionEntry, $user->id, $vendorHONo, $vendorHOName);
                }

            }            
        }

        $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }

    private function createEntries($entries, $userId, ?string $apsysMainVendorNo = null, ?string $apsysMainVendorName = null)
    {
        $entries = EntryBranchNumber::normalizeEntriesForCurrentCompany($entries);

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
                'ent_amt_per_acc'   => $entry['amount'],
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
        }

        $groupedEntries = collect($entries)->groupBy('apsys_entry_main_id');
        foreach ($groupedEntries as $mainId => $groupedEntry) {
            $entry = $groupedEntry->first();
            ApsysEntryMain::create([
                'ent_tran_no'       => $mainId,
                'ven_no'            => $apsysMainVendorNo ?? $entry['ven_no'],
                'ven_name'          => $apsysMainVendorName ?? $entry['ven_name'],
                'ent_doc_date'      => now(),
                'branch_id'         => $entry['branch_number'],
                'branch_name'       => $entry['branch_name'],
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
