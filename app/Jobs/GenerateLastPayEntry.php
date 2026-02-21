<?php

namespace App\Jobs;

use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Entry;
use App\Models\LastPay;
use App\Models\LastPayDeduction;
use App\Models\LastPaySetup;
use App\Models\OtherCompany\OtherEmployee;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\NotifiesJobStatus;

class GenerateLastPayEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;
    protected $lastPay;
    protected $user;
    private $counter;
    /**
     * Create a new job instance.
     */
    public function __construct(LastPay $lastPay, $user)
    {
        $this->lastPay = $lastPay;
        $this->user = $user;
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

    public function handle(): void
    {
        try {
        $lastPay = $this->lastPay;

        $userId = $this->user;
        $user = User::find($userId);
        $employee_id = "";
        if ($user->employee) {
            $employee_id = $user->employee->id;
        } else {
            $employee = OtherEmployee::where("employee_number", $user->employee_number)->first();
            $employee_id = $employee->id;
        }
        

        $lastPaySetups = LastPaySetup::all()->keyBy('setup_type');

        $thirteenthMonthDebit  = $lastPaySetups['13th']->debit_id;
        $thirteenthMonthCredit = $lastPaySetups['13th']->credit_id;

        $slVlDebit  = $lastPaySetups['slvl']->debit_id;
        $slVlCredit = $lastPaySetups['slvl']->credit_id;

        $taxrefundDebit  = $lastPaySetups['taxrefund']->debit_id;
        $taxrefundCredit = $lastPaySetups['taxrefund']->credit_id;

        $incentivesCredit = $lastPaySetups['incentives']->credit_id;

        $salesDebit  = $lastPaySetups['sales']->debit_id;
        $salesCredit = $lastPaySetups['sales']->credit_id;

        $mealDebit  = $lastPaySetups['meal']->debit_id;
        $mealCredit = $lastPaySetups['meal']->credit_id;

        $storeIncentivesDebit  = $lastPaySetups['storeincentives']->debit_id;
        $storeIncentivesCredit = $lastPaySetups['storeincentives']->credit_id;

        $sssLoansDebit  = $lastPaySetups['sssloans']->debit_id;
        $sssLoansCredit = $lastPaySetups['sssloans']->credit_id;

        $sssLoansRemittanceDebit  = $lastPaySetups['sssloansremittance']->debit_id;
        $sssLoansRemittanceCredit = $lastPaySetups['sssloansremittance']->credit_id;
        $sssLoansRemittanceVendor = $lastPaySetups['sssloansremittance']->vendor_id;

        $branchHO = Branch::where('short_name', 'HO')->first();
        $branchHOId = $branchHO->id;
        $branchHOName = $branchHO->name;
        $branchHONumber = $branchHO->branch_number;

        $employee = Employee::where('id', $lastPay->employee_id)->first();
        $employeeFullName = $employee->full_name;
        $employeeNumber = $employee->employee_number;

        $branch = Branch::where('id', $employee->branch_id)->first();
        $branchId = $branch->id;
        $branchName = $branch->name;
        $branchNumber = $branch->branch_number;

        $lastPayDeductions = LastPayDeduction::where('last_pay_id', $lastPay->id)
            ->where('employee_id', $lastPay->employee_id)
            ->get();

        $entries = [];

        if ($lastPay->thirteenth_month > 0) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $entries = array_merge($entries, [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->thirteenth_month,
                    'acc_no'              => $thirteenthMonthDebit,
                    'description_per_acc' => 'Last Pay|13th Month Pay',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->thirteenth_month,
                    'acc_no'              => $thirteenthMonthCredit,
                    'description_per_acc' => 'Last Pay|13th Month Pay',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ]
            ]);
        }

        if ($lastPay->sl_vl > 0) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $entries = array_merge($entries, [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->sl_vl,
                    'acc_no'              => $slVlDebit,
                    'description_per_acc' => 'Last Pay|SL/VL',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->sl_vl,
                    'acc_no'              => $slVlCredit,
                    'description_per_acc' => 'Last Pay|SL/VL',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ]
            ]);
        }

        if ($lastPay->tax_refund > 0) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $entries = array_merge($entries, [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->tax_refund,
                    'acc_no'              => $taxrefundDebit,
                    'description_per_acc' => 'Last Pay|Tax Refund',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->tax_refund,
                    'acc_no'              => $taxrefundCredit,
                    'description_per_acc' => 'Last Pay|Tax Refund',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ]
            ]);
        }

        if ($lastPay->incentives > 0) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $entries = array_merge($entries, [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->incentives,
                    'acc_no'              => $branch->advance_debit,
                    'description_per_acc' => 'Last Pay|Incentives',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->incentives,
                    'acc_no'              => $incentivesCredit,
                    'description_per_acc' => 'Last Pay|Incentives',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ]
            ]);
        }

        if ($lastPay->incentives > 0) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $entries = array_merge($entries, [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchId,
                    'branch_number'       => $branchNumber,
                    'branch_name'         => $branchName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->incentives,
                    'acc_no'              => $storeIncentivesDebit,
                    'description_per_acc' => 'Last Pay|Incentives',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchId,
                    'branch_number'       => $branchNumber,
                    'branch_name'         => $branchName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->incentives,
                    'acc_no'              => $storeIncentivesCredit,
                    'description_per_acc' => 'Last Pay|Incentives',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ]
            ]);
        }

        if ($lastPay->sales > 0) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $entries = array_merge($entries, [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->sales,
                    'acc_no'              => $salesDebit,
                    'description_per_acc' => 'Last Pay|Sales Incentives',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->sales,
                    'acc_no'              => $salesCredit,
                    'description_per_acc' => 'Last Pay|Sales Incentives',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ]
            ]);
        }

        if ($lastPay->meal > 0) {
            $entryId = $this->generateEntryId($counterEntry);
            $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
            $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
            $entries = array_merge($entries, [
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->meal,
                    'acc_no'              => $mealDebit,
                    'description_per_acc' => 'Last Pay|Meal Allowance',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $entryId,
                    'apsys_entry_id'      => $apsysEntryId,
                    'apsys_entry_main_id' => $apsysEntryMainId,
                    'document_no'         => $lastPay->last_pay_number . '|Head Office',
                    'branch_id'           => $branchHOId,
                    'branch_number'       => $branchHONumber,
                    'branch_name'         => $branchHOName,
                    'ven_no'              => $employeeNumber,
                    'ven_name'            => $employeeFullName,
                    'amount'              => $lastPay->meal,
                    'acc_no'              => $mealCredit,
                    'description_per_acc' => 'Last Pay|Meal Allowance',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ]
            ]);
        }

        //deductions
        foreach ($lastPayDeductions as $lastPayDeduction) {
            $employeeDeduction = EmployeeDeduction::with('deduction')
            ->where('employee_id', optional($lastPayDeduction)->employee_id)
            ->where('reference_doc', optional($lastPayDeduction)->reference_doc)
            ->where('balance', '>=', 1)
            ->where('end_loan_status', '!=', 'Yes')
            ->first();

            if (!$employeeDeduction) {
                continue;
            }

            if ($employeeDeduction->deduction->category === 'SSS') {
                $entryId = $this->generateEntryId($counterEntry);
                $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
                $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employeeDeduction->reference_doc . '|Head Office',
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $employeeNumber,
                        'ven_name'            => $employeeFullName,
                        'amount'              => $employeeDeduction->balance,
                        'acc_no'              => $sssLoansDebit,
                        'description_per_acc' => 'Last Pay|Deduction',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employeeDeduction->reference_doc . '|Head Office',
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $employeeNumber,
                        'ven_name'            => $employeeFullName,
                        'amount'              => $employeeDeduction->balance,
                        'acc_no'              => $sssLoansCredit,
                        'description_per_acc' => 'Last Pay|Deduction',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employeeDeduction->reference_doc . '|Head Office',
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchName,
                        'ven_no'              => $sssLoansRemittanceVendor,
                        'ven_name'            => $employeeFullName,
                        'amount'              => $employeeDeduction->balance,
                        'acc_no'              => $sssLoansRemittanceDebit,
                        'description_per_acc' => 'Last Pay|Deduction',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employeeDeduction->reference_doc . '|Head Office',
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchName,
                        'ven_no'              => $sssLoansRemittanceVendor,
                        'ven_name'            => $employeeFullName,
                        'amount'              => $employeeDeduction->balance,
                        'acc_no'              => $sssLoansRemittanceCredit,
                        'description_per_acc' => 'Last Pay|Deduction',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            } else {
                $entryId = $this->generateEntryId($counterEntry);
                $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
                $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employeeDeduction->reference_doc . '|Head Office',
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchName,
                        'ven_no'              => $employeeNumber,
                        'ven_name'            => $employeeFullName,
                        'amount'              => $employeeDeduction->balance,
                        'acc_no'              => $sssLoansDebit,
                        'description_per_acc' => 'Last Pay|Deduction',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employeeDeduction->reference_doc . '|Head Office',
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'branch_name'         => $branchName,
                        'ven_no'              => $employeeNumber,
                        'ven_name'            => $employeeFullName,
                        'amount'              => $employeeDeduction->balance,
                        'acc_no'              => $sssLoansCredit,
                        'description_per_acc' => 'Last Pay|Deduction',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            }
        }

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
                'generated_by'               => $employee_id,
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
                'ent_amt_per_acc'    => $entry['amount'],
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

        $this->notifyJobFinished();
        } catch (\Throwable $e) {
            $this->notifyJobFailed();
            throw $e;
        }
    }
}
