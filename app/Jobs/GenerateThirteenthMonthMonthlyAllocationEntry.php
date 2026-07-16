<?php

namespace App\Jobs;

use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Accounting\Vendor;
use App\Models\Branch;
use App\Models\Entry;
use App\Support\EntryBranchNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Support\NotifiesJobStatus;

class GenerateThirteenthMonthMonthlyAllocationEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;
    protected $groupedBranches;
    protected $user;
    protected $month;
    protected $year;
    private $counter;
    /**
     * Create a new job instance.
     */
    public function __construct($groupedBranches, $user, $month, $year)
    {
        $this->groupedBranches = $groupedBranches;
        $this->user = $user;
        $this->month = $month;
        $this->year = $year;
    }

    // public function middleware()
    // {
    //     return [
    //         (new WithoutOverlapping('accounting_psd_sequence'))
    //             ->shared()
    //             ->releaseAfter(1200)
    //             ->expireAfter(7200)
    //     ];
    // }

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

    public function handle(): void
    {
        try {
            $groupedBranches = $this->groupedBranches;
            $user = $this->user;
            $month = $this->month;
            $year = $this->year;

            $vendor = Vendor::where("ven_name", "HO")->first();
            $vendorHONo = $vendor->ven_no;
            $vendorHOName = $vendor->ven_name;

            foreach ($groupedBranches as $branches) {
                $branch = Branch::where('id', $branches->branch_id)->first();

                $thirteenthMonth = $branches->branch_net_claim_13th_month;
                $payLeaveAndAdditional = ($branches->branch_net_claim_pay_leave) + ($branches->branch_net_claim_additional);
                $total = $branches->branch_total;

                $branchId = $branch->id;
                $branchName = $branch->name;
                $branchNumber = $branch->branch_number;

                $firstDebitAccNo = $branch->branch_ho_13th_debit;
                $secondDebitAccNo = $branch->branch_ho_13th_debit_1;
                $creditAccNo = $branch->branch_ho_13th_credit;

                $entries = [];

                if ($total > 0) {

                    $entryId = $this->generateEntryId($counterEntry);
                    $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
                    $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);

                    $entries = [
                        [
                            'entry_id'            => $entryId,
                            'apsys_entry_id'      => $apsysEntryId,
                            'apsys_entry_main_id' => $apsysEntryMainId,
                            'document_no'         => "{$month} {$year} 13th Month Allocation",
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'branch_name'         => $branchName,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'total_amount'        => $total,
                            'amount'              => $thirteenthMonth,
                            'acc_no'              => $firstDebitAccNo,
                            'description_per_acc' => "13th Month Allocation | {$month} {$year}",
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $entryId,
                            'apsys_entry_id'      => $apsysEntryId,
                            'apsys_entry_main_id' => $apsysEntryMainId,
                            'document_no'         => "{$month} {$year} 13th Month Allocation",
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'branch_name'         => $branchName,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'total_amount'        => $total,
                            'amount'              => $payLeaveAndAdditional,
                            'acc_no'              => $secondDebitAccNo,
                            'description_per_acc' => "13th Month Allocation | {$month} {$year}",
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $entryId,
                            'apsys_entry_id'      => $apsysEntryId,
                            'apsys_entry_main_id' => $apsysEntryMainId,
                            'document_no'         => "{$month} {$year} 13th Month Allocation",
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'branch_name'         => $branchName,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'total_amount'        => $total,
                            'amount'              => $total,
                            'acc_no'              => $creditAccNo,
                            'description_per_acc' => "13th Month Allocation | {$month} {$year}",
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ];
                }
                $this->createEntries($entries, $user);
            }

            $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }

    private function createEntries($entries, $user)
    {
        $entries = EntryBranchNumber::normalizeEntriesForCurrentCompany($entries);

        $groupedEntries = collect($entries)->groupBy('apsys_entry_main_id');

        foreach ($groupedEntries as $mainId => $groupedEntry) {
            $entry = $groupedEntry->first();

            $entryMain = ApsysEntryMain::create([
                'ent_tran_no'       => $mainId,
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

            foreach ($groupedEntry as $ent) {
                Entry::create([
                    'entry_id'                   => $entryMain->ent_tran_no,
                    'document_no'                => $ent['document_no'],
                    'branch_id'                  => $ent['branch_id'],
                    'branch_number'              => $ent['branch_number'],
                    'vendor_no'                  => $ent['ven_no'],
                    'amount'                     => $ent['amount'],
                    'account_no'                 => $ent['acc_no'],
                    'description'                => $ent['description_per_acc'],
                    'balance_type'               => $ent['balance_type'],
                    'is_block_payment'           => $ent['is_block_payment'],
                    'status'                     => 'Unapplied',
                    'generated_at'               => now(),
                    'generated_by'               => $user->id,
                ]);

                ApsysEntry::create([
                    'ent_tran_no'       => $entryMain->ent_tran_no,
                    'branch_id'         => $ent['branch_number'],
                    'ent_post_date'     => now(),
                    'ven_no'            => $ent['ven_no'],
                    'ent_doc_no'        => $ent['document_no'],
                    'ent_doc_date'      => now(),
                    'ent_due_date'      => now(),
                    'ent_header'        => '',
                    'ent_crf_no'        => '',
                    'ent_trans_amt'     => $ent['total_amount'],
                    'ent_amt_due'       => $ent['amount'],
                    'ent_module'        => 'Payroll',
                    'acc_no'            => $ent['acc_no'],
                    'ent_amt_per_acc'   => $ent['amount'],
                    'ent_desc_per_acc'  => $ent['description_per_acc'],
                    'ent_norm_bal'      => $ent['balance_type'],
                    'ent_total'         => $ent['amount'],
                    'ent_module_type'   => 'Single',
                    'ent_app_amt'       => 0,
                    'ent_unapp_amt'     => $ent['amount'],
                    'ent_vat_amt'       => 0,
                    'ent_nonvat_amt'    => 0,
                    'ent_na_amt'        => $ent['amount'],
                    'ent_inp_tax_amt'   => 0,
                    'ent_block_payment' => ($ent['is_block_payment'] == 1) ? 'Yes' : 'No',
                    'ent_status'        => 'Unapplied',
                    'ent_chk'           => 'No',
                    'date_added'        => now(),
                ]);
            }
        }
    }
}
