<?php

namespace App\Jobs;

use App\Models\Accounting\Account;
use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Accounting\Vendor;
use App\Models\AdditionalIncome;
use App\Models\BranchAdditionalIncome;
use App\Models\Branch;
use App\Models\ContributionAndTax;
use App\Models\Deduction;
use App\Models\EmployeeDeduction;
use App\Models\Entry;
use App\Models\Incentives;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use App\Support\NotifiesJobStatus;
use Log;
use DB;

class GenerateEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;
    protected $payroll;
    protected $pay_date;
    protected $start_date;
    protected $end_date;
    protected $user;
    private $counterEntry;
    private $counterApsysEntry;
    private $counterApsysEntryMain;
    /**
     * Create a new job instance.
     */
    public function __construct(Collection $payroll, $pay_date, $start_date, $end_date, $user)
    {
        $this->payroll = $payroll;
        $this->pay_date = $pay_date;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
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
        
        $vendorNames = [
            'EESSS',
            'EEPH',
            'EEPG',
            'ERSSS',
            'ERPH',
            'ERPG',
            'WTAX',
            'ADJUSTMENT'
        ];

        foreach ($vendorNames as $vendorName) {

            $record = ContributionAndTax::where('vendor_name', $vendorName)->first();

            if ($record) {
                $vendor = Vendor::where('ven_no', $record->vendor_id)->first();
                $vendorNameFromDB = $vendor ? $vendor->ven_name : null;

                if ($vendorName === 'EESSS') {
                    $eeSSSVendor = $record->vendor_id;
                    $eeSSSDebit  = $record->debit_id;
                    $eeSSSCredit = $record->credit_id;
                    $eeSSSVendorName = $vendorNameFromDB;
                }

                if ($vendorName === 'EEPH') {
                    $eePhilHealthVendor = $record->vendor_id;
                    $eePhilHealthDebit  = $record->debit_id;
                    $eePhilHealthCredit = $record->credit_id;
                    $eePhilHealthVendorName = $vendorNameFromDB;
                }

                if ($vendorName === 'EEPG') {
                    $eePagibigVendor = $record->vendor_id;
                    $eePagibigDebit  = $record->debit_id;
                    $eePagibigCredit = $record->credit_id;
                    $eePagibigVendorName = $vendorNameFromDB;
                }

                if ($vendorName === 'ERSSS') {
                    $erSSSVendor = $record->vendor_id;
                    $erSSSDebit  = $record->debit_id;
                    $erSSSCredit = $record->credit_id;
                    $erSSSVendorName = $vendorNameFromDB;
                }

                if ($vendorName === 'ERPH') {
                    $erPhilHealthVendor = $record->vendor_id;
                    $erPhilHealthDebit  = $record->debit_id;
                    $erPhilHealthCredit = $record->credit_id;
                    $erPhilHealthVendorName = $vendorNameFromDB;
                }

                if ($vendorName === 'ERPG') {
                    $erPagibigVendor = $record->vendor_id;
                    $erPagibigDebit  = $record->debit_id;
                    $erPagibigCredit = $record->credit_id;
                    $erPagibigVendorName = $vendorNameFromDB;
                }

                if ($vendorName === 'WTAX') {
                    $withHoldingTaxVendor = $record->vendor_id;
                    $withHoldingTaxDebit  = $record->debit_id;
                    $withHoldingTaxCredit = $record->credit_id;
                    $withHoldingTaxVendorName = $vendorNameFromDB;
                }

                if ($vendorName === 'ADJUSTMENT') {
                    $adjustmentVendor = $record->vendor_id;
                    $adjustmentDebit  = $record->debit_id;
                    $adjustmentCredit = $record->credit_id;
                    $adjustmentVendorName = $vendorNameFromDB;
                }
            }
        }

        $vendor = Vendor::where("ven_name", "HO")->first();
        $vendorHONo = $vendor->ven_no;
        $vendorHOName = $vendor->ven_name;

        $accountNames = [
            'TEMPORARY ACCOUNT A',
            'TEMPORARY ACCOUNT B',
            'Accounts Payable',
        ];

        $accountsQuery = Account::whereIn('acc_name', $accountNames)->get();

        foreach ($accountsQuery as $acc) {

            if ($acc->acc_name === 'TEMPORARY ACCOUNT A') {
                $temporaryAccountA = $acc->acc_no;
            }

            if ($acc->acc_name === 'TEMPORARY ACCOUNT B') {
                $temporaryAccountB = $acc->acc_no;
            }

            if ($acc->acc_name === 'Accounts Payable') {
                $accountsPayable      = $acc->acc_no;
                $accountsPayableName  = $acc->acc_name;
            }
        }

        $additionalIncome = AdditionalIncome::where("description", "Incentives")->first();

        $debitStoreId = $additionalIncome->store_debit_id;
        $creditStoreId = $additionalIncome->store_credit_id;

        $userId = $this->user;
        $user = User::find($userId);
        $employee_id = $user->employee->id;

        $branchHO = Branch::where('short_name', 'HO')
            ->first();
        $branchHOId = $branchHO->id;
        $branchHOName = $branchHO->name;
        $branchHOShortName = $branchHO->short_name;
        $branchHONumber = $branchHO->branch_number;

        $start_date = $this->start_date;
        $end_date = $this->end_date;

        $payrollBranchIds = Payroll::distinct()
            ->where('pay_date', $this->pay_date)
            ->pluck('timekeeping_branch_id');

        foreach ($payrollBranchIds as $payrollBranchId) {
            $payrollBranch = Branch::with([
                'expense_debit',
                'expense_credit',
                'advance_debit',
                'advance_credit'
            ])->find($payrollBranchId);

            $branchId = $payrollBranch->id;
            $branchName = $payrollBranch->name;
            $branchShortName = $payrollBranch->short_name;
            $branchNumber = $payrollBranch->branch_number;

            $payrollEmployeeBankQuery = Payroll::where('timekeeping_branch_id', $branchId)
                ->whereIn('employee_id', function($query) {
                    $query->select('employee_id')
                        ->from('payroll_list')
                        ->where('is_bank', 1);
                })
                ->get();

            $payrollEmployeeBankSums = [
                'grosspay' => 0,
                'eeSSS' => 0,
                'erSSS' => 0,
                'eePh' => 0,
                'erPh' => 0,
                'eePagibig' => 0,
                'erPagibig' => 0,
                'withHoldingTax' => 0,
                'netTaxableAdjustment' => 0,
            ];

            $payrollEmployeeBank = $payrollEmployeeBankQuery->first();

            foreach ($payrollEmployeeBankQuery as $payrollBank) {
                $payrollEmployeeBankSums['grosspay'] += floatval(str_replace(',', '', $payrollBank->gross_pay));
                $payrollEmployeeBankSums['eeSSS'] += floatval(str_replace(',', '', $payrollBank->employee_sss));
                $payrollEmployeeBankSums['erSSS'] += floatval(str_replace(',', '', $payrollBank->employer_sss));
                $payrollEmployeeBankSums['eePh'] += floatval(str_replace(',', '', $payrollBank->employee_philhealth));
                $payrollEmployeeBankSums['erPh'] += floatval(str_replace(',', '', $payrollBank->employer_philhealth));
                $payrollEmployeeBankSums['eePagibig'] += floatval(str_replace(',', '', $payrollBank->employee_pagibig));
                $payrollEmployeeBankSums['erPagibig'] += floatval(str_replace(',', '', $payrollBank->employer_pagibig));
                $payrollEmployeeBankSums['withHoldingTax'] += floatval(str_replace(',', '', $payrollBank->withholding_tax));
                $payrollEmployeeBankSums['netTaxableAdjustment'] += floatval(str_replace(',', '', $payrollBank->non_taxable_adjustment));
            }

            $bankDeductionSum = collect();
            $bankTotalMonthlyAmortization = 0;

            foreach ($payrollEmployeeBankQuery as $payrollBankDeduction) {
                if ($payrollBankDeduction->total_deductions > 0) {
                    $bankDeductions = json_decode($payrollBankDeduction->deductions, true);
                    if (!$bankDeductions) continue;

                    foreach ($bankDeductions as $bankDeductionJson) {

                        $employeeId = $payrollBankDeduction->employee_id;
                        $bankDeductionId = $bankDeductionJson['deduction_id'] ?? null;

                        $employeeBankDeduction = EmployeeDeduction::find($bankDeductionId);

                        if (!$employeeBankDeduction){
                            continue;
                        }

                        $monthlyAmortization = floatval(str_replace(',', '', $bankDeductionJson['monthly_amortization'] ?? 0));

                        $bankTotalMonthlyAmortization += $monthlyAmortization;

                        $employeeBankDeductionId = $employeeBankDeduction->deduction_id;

                        $bankDeductionModel = Deduction::with([
                            'amortization_debit',
                            'amortization_credit',
                            'amortization_vendor'
                        ])->find($employeeBankDeductionId);

                        $vendor = Vendor::where('ven_no', $bankDeductionModel->amortization_vendor_id)->first();
                        $bankDeductionSumsVenName = $vendor->ven_name ?? null;

                        $bankDeductionSum->push((object)[
                            'employee_id' => $employeeId,
                            'deduction_id' => $bankDeductionId,
                            'deduction' => $bankDeductionModel,
                            'ven_name' => $bankDeductionSumsVenName,
                        ]);
                    }
                }
            }

            $bankDeductionSums = $bankDeductionSum->first();
            $bankTotalAmortization = $bankTotalMonthlyAmortization;

            $bankEmployeeIds = $payrollEmployeeBankQuery->pluck('employee_id');
            $bankIncentiveSums = Incentives::whereIn('employee_id', $bankEmployeeIds)
                ->where('pay_date', $this->pay_date) 
                ->sum('incentive_amount');

            $bankAdditionalIncomeSums = BranchAdditionalIncome::where('branch_id', $branchId)
                ->where('pay_date', $this->pay_date) 
                ->sum('amount');

            $bankAdditionalAndIncentiveSums = $bankIncentiveSums + $bankAdditionalIncomeSums;

            $isPayrollEmployeeCashOnHold = Payroll::where('timekeeping_branch_id', $branchId)
                ->whereIn('employee_id', function($query) {
                    $query->select('employee_id')
                        ->from('payroll_list')
                        ->where('is_bank', 0)
                        ->where('is_hold', 1);
                })
                ->exists() ? 1 : 0;

            $payrollEmployeeCashQuery = Payroll::with(['employee.vendor:ven_no,ven_name', 'employee', 'timekeeping_branch'])
                ->where('timekeeping_branch_id', $branchId)
                ->whereIn('employee_id', function($query) {
                    $query->select('employee_id')
                        ->from('payroll_list')
                        ->where('is_bank', 0);
                })
                ->get();

            $payrollEmployeeCashSums = [
                'grosspay' => 0,
                'eeSSS' => 0,
                'erSSS' => 0,
                'eePh' => 0,
                'erPh' => 0,
                'eePagibig' => 0,
                'erPagibig' => 0,
                'withHoldingTax' => 0,
                'netTaxableAdjustment' => 0,
            ];

            $payrollEmployeeCash = $payrollEmployeeCashQuery->first();
            $payrollEmployeeCashVenName = $payrollEmployeeCash->employee->vendor->ven_name ?? null;

            foreach ($payrollEmployeeCashQuery as $payrollCash) {
                $payrollEmployeeCashSums['grosspay'] += floatval(str_replace(',', '', $payrollCash->gross_pay));
                $payrollEmployeeCashSums['eeSSS'] += floatval(str_replace(',', '', $payrollCash->employee_sss));
                $payrollEmployeeCashSums['erSSS'] += floatval(str_replace(',', '', $payrollCash->employer_sss));
                $payrollEmployeeCashSums['eePh'] += floatval(str_replace(',', '', $payrollCash->employee_philhealth));
                $payrollEmployeeCashSums['erPh'] += floatval(str_replace(',', '', $payrollCash->employer_philhealth));
                $payrollEmployeeCashSums['eePagibig'] += floatval(str_replace(',', '', $payrollCash->employee_pagibig));
                $payrollEmployeeCashSums['erPagibig'] += floatval(str_replace(',', '', $payrollCash->employer_pagibig));
                $payrollEmployeeCashSums['withHoldingTax'] += floatval(str_replace(',', '', $payrollCash->withholding_tax));
                $payrollEmployeeCashSums['netTaxableAdjustment'] += floatval(str_replace(',', '', $payrollCash->non_taxable_adjustment));
            }

            $cashDeductionSum = collect();
            $cashTotalMonthlyAmortization = 0;

            foreach ($payrollEmployeeCashQuery as $payrollCashDeduction) {
                if ($payrollCashDeduction->total_deductions > 0) {
                    $cashDeductions = json_decode($payrollCashDeduction->deductions, true);
                    if (!$cashDeductions) continue;

                    foreach ($cashDeductions as $cashDeductionJson) {

                        $employeeId = $payrollCashDeduction->employee_id;
                        $cashDeductionId = $cashDeductionJson['deduction_id'] ?? null;

                        $employeeCashDeduction = EmployeeDeduction::find($cashDeductionId);

                        if (!$employeeCashDeduction){
                            continue;
                        }

                        $monthlyAmortization = floatval(str_replace(',', '', $cashDeductionJson['monthly_amortization'] ?? 0));

                        $cashTotalMonthlyAmortization += $monthlyAmortization;

                        $employeeCashDeductionId = $employeeCashDeduction->deduction_id;

                        $cashDeductionModel = Deduction::with([
                            'amortization_debit',
                            'amortization_credit',
                            'amortization_vendor'
                        ])->find($employeeCashDeductionId);

                        $vendor = Vendor::where('ven_no', $cashDeductionModel->amortization_vendor_id)->first();
                        $cashDeductionSumsVenName = $vendor->ven_name ?? null;

                        $cashDeductionSum->push((object)[
                            'employee_id' => $employeeId,
                            'deduction_id' => $cashDeductionId,
                            'deduction' => $cashDeductionModel,
                            'ven_name' => $cashDeductionSumsVenName,
                        ]);
                    }
                }
            }

            $cashDeductionSums = $cashDeductionSum->first();
            $cashTotalAmortization = $cashTotalMonthlyAmortization;

            $cashEmployeeIds = $payrollEmployeeCashQuery->pluck('employee_id');
            $cashIncentiveSums = Incentives::whereIn('employee_id', $cashEmployeeIds)
                ->where('pay_date', $this->pay_date) 
                ->sum('incentive_amount');

            $entries = [];

            if ($payrollBranch->type === "Head Office") {
                //head office - grosspay (cash and bank)
                if ($payrollEmployeeBankSums['grosspay'] > 0 && $payrollBranch->branch_number != "SFI00001") {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);

                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->expense_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                //head office - employee statutories (bank)
                if ($payrollEmployeeBankSums['eeSSS'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $eeSSSDebit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $eeSSSCredit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeBankSums['eePh'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $eePhilHealthDebit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $eePhilHealthCredit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeBankSums['eePagibig'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $eePagibigDebit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $eePagibigCredit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                //head office - deductions (bank)
                if ($bankTotalAmortization > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $bankDeductionSums->deduction->amortization_debit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $bankDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $bankDeductionSums->ven_name,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $bankDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $bankDeductionSums->ven_name,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $bankDeductionSums->deduction->amortization_credit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - with_holding_tax (bank)
                if ($payrollEmployeeBankSums['withHoldingTax'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxDebit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxCredit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - employer statutories (bank)
                if ($payrollEmployeeBankSums['erSSS'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'ven_name'            => $erSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'ven_name'            => $erSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $erSSSCredit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $erSSSDebit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                if ($payrollEmployeeBankSums['erPh'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'ven_name'            => $erPhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'ven_name'            => $erPhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $erPhilHealthCredit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $erPhilHealthDebit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                if ($payrollEmployeeBankSums['erPagibig'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'ven_name'            => $erPagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'ven_name'            => $erPagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $erPagibigCredit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $erPagibigDebit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - net taxable adjustment (bank)
                if ($payrollEmployeeBankSums['netTaxableAdjustment'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $adjustmentVendor,
                            'ven_name'            => $adjustmentVendorName,
                            'amount'              => $payrollEmployeeBankSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentDebit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $adjustmentVendor,
                            'ven_name'            => $adjustmentVendorName,
                            'amount'              => $payrollEmployeeBankSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentCredit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - incentives amount (bank)
                if ($bankIncentiveSums > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $creditStoreId,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $debitStoreId,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - grosspay (cash)
                if ($payrollEmployeeCashSums['grosspay'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);

                    $grosspayEntries = [];

                    if ($payrollBranch->branch_number != "SFI00001") {
                        $grosspayEntries = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ];

                        $grosspayEntries = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ];
                    }
                        
                    $grosspayEntries = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => $start_date . '-' . $end_date,
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $payrollEmployeeCashSums['grosspay'],
                        'acc_no'              => $payrollBranch->expense_debit,
                        'description_per_acc' => 'Grosspay',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                    ];
                    $grosspayEntries = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => $start_date . '-' . $end_date,
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $payrollEmployeeCashSums['grosspay'],
                        'acc_no'              => $payrollBranch->expense_credit,
                        'description_per_acc' => 'Grosspay',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                    ];
                    
                    $entries = array_merge($entries, $grosspayEntries);
                }

                //head office - employee statutories (cash)
                if ($payrollEmployeeCashSums['eeSSS'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $eeSSSDebit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $eeSSSCredit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeCashSums['eePh'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $eePhilHealthDebit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $eePhilHealthCredit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeCashSums['eePagibig'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $eePagibigDebit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $eePagibigCredit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                //head office - deduction (cash)
                if ($cashTotalAmortization > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $cashDeductionSums->deduction->amortization_debit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $cashDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $cashDeductionSums->ven_name,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $cashDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $cashDeductionSums->ven_name,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $cashDeductionSums->deduction->amortization_credit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - with_holding_tax (cash)
                if ($payrollEmployeeCashSums['withHoldingTax'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxDebit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxCredit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - employer statutories (cash)
                if ($payrollEmployeeCashSums['erSSS'] > 0) {
                    if ($payrollBranch->branch_number != "SFI00001") {
                        $firstEntry = $this->generateEntryId($this->counterEntry);
                        $secondEntry = $this->generateEntryId($this->counterEntry);

                        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $entries = array_merge($entries, [
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                                'branch_id'           => $branchHOId,
                                'branch_name'         => $branchHOName,
                                'branch_number'       => $branchHONumber,
                                'ven_no'              => $erSSSVendor,
                                'ven_name'            => $erSSSVendorName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $payrollBranch->advance_debit,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                                'branch_id'           => $branchHOId,
                                'branch_name'         => $branchHOName,
                                'branch_number'       => $branchHONumber,
                                'ven_no'              => $erSSSVendor,
                                'ven_name'            => $erSSSVendorName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $erSSSCredit,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],

                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $erSSSDebit,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                            ],
                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $payrollBranch->expense_credit,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                            ],
                        ]);
                    } else {
                        $firstEntry = $this->generateEntryId($this->counterEntry);
                        $secondEntry = $this->generateEntryId($this->counterEntry);

                        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $entries = array_merge($entries, [
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $erSSSDebit,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $accountsPayable,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],

                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $erSSSVendor,
                                'ven_name'            => $erSSSVendorName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $erSSSDebit,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $erSSSVendor,
                                'ven_name'            => $erSSSVendorName,
                                'amount'              => $payrollEmployeeCashSums['erSSS'],
                                'acc_no'              => $erSSSCredit,
                                'description_per_acc' => 'Employer SSS Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],
                        ]);
                    }
                }

                if ($payrollEmployeeCashSums['erPh'] > 0) {
                    if ($payrollBranch->branch_number != "SFI00001") {
                        $firstEntry = $this->generateEntryId($this->counterEntry);
                        $secondEntry = $this->generateEntryId($this->counterEntry);

                        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $entries = array_merge($entries, [
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                                'branch_id'           => $branchHOId,
                                'branch_name'         => $branchHOName,
                                'branch_number'       => $branchHONumber,
                                'ven_no'              => $erPhilHealthVendor,
                                'ven_name'            => $erPhilHealthVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $payrollBranch->advance_debit,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                                'branch_id'           => $branchHOId,
                                'branch_name'         => $branchHOName,
                                'branch_number'       => $branchHONumber,
                                'ven_no'              => $erPhilHealthVendor,
                                'ven_name'            => $erPhilHealthVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $erPhilHealthCredit,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],

                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $erPhilHealthDebit,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                            ],
                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $payrollBranch->expense_credit,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                            ],
                        ]);
                    } else {
                        $firstEntry = $this->generateEntryId($this->counterEntry);
                        $secondEntry = $this->generateEntryId($this->counterEntry);

                        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $entries = array_merge($entries, [
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $erPhilHealthDebit,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $accountsPayable,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],

                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $erPhilHealthVendor,
                                'ven_name'            => $erPhilHealthVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $erPhilHealthDebit,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $erPhilHealthVendor,
                                'ven_name'            => $erPhilHealthVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPh'],
                                'acc_no'              => $erPhilHealthCredit,
                                'description_per_acc' => 'Employer Philhealth Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],
                        ]);
                    }
                }


                //dito ako natapos
                if ($payrollEmployeeCashSums['erPagibig'] > 0) {
                    if ($payrollBranch->branch_number != "SFI00001") {
                        $firstEntry = $this->generateEntryId($this->counterEntry);
                        $secondEntry = $this->generateEntryId($this->counterEntry);

                        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $entries = array_merge($entries, [
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                                'branch_id'           => $branchHOId,
                                'branch_name'         => $branchHOName,
                                'branch_number'       => $branchHONumber,
                                'ven_no'              => $erPagibigVendor,
                                'ven_name'            => $erPagibigVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $payrollBranch->advance_debit,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                                'branch_id'           => $branchHOId,
                                'branch_name'         => $branchHOName,
                                'branch_number'       => $branchHONumber,
                                'ven_no'              => $erPagibigVendor,
                                'ven_name'            => $erPagibigVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $erPagibigCredit,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],

                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $erPagibigDebit,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                            ],
                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $payrollBranch->expense_credit,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                            ],
                        ]);
                    } else {
                        $firstEntry = $this->generateEntryId($this->counterEntry);
                        $secondEntry = $this->generateEntryId($this->counterEntry);

                        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                        $entries = array_merge($entries, [
                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $erPagibigDebit,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],

                            [
                                'entry_id'            => $firstEntry,
                                'apsys_entry_id'      => $firstApsysEntry,
                                'apsys_entry_main_id' => $firstApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $vendorHONo,
                                'ven_name'            => $vendorHOName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $accountsPayable,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],

                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $erPagibigVendor,
                                'ven_name'            => $erPagibigVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $erPagibigDebit,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Debit',
                                'is_block_payment'    => 0,
                            ],
                            [
                                'entry_id'            => $secondEntry,
                                'apsys_entry_id'      => $secondApsysEntry,
                                'apsys_entry_main_id' => $secondApsysEntryMain,
                                'document_no'         => $start_date . '-' . $end_date,
                                'branch_id'           => $branchId,
                                'branch_name'         => $branchName,
                                'branch_number'       => $branchNumber,
                                'ven_no'              => $erPagibigVendor,
                                'ven_name'            => $erPagibigVendorName,
                                'amount'              => $payrollEmployeeCashSums['erPagibig'],
                                'acc_no'              => $erPagibigCredit,
                                'description_per_acc' => 'Employer Pag-Ibig Contribution',
                                'balance_type'        => 'Credit',
                                'is_block_payment'    => 0,
                            ],
                        ]);
                    }
                }

                //head office - net taxable adjustment (cash)
                if ($payrollEmployeeCashSums['netTaxableAdjustment'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentDebit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentCredit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //head office - incentives (cash)
                if ($cashIncentiveSums > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $incentiveEntries = [];

                    if ($payrollBranch->branch_number != "SFI00001") {

                        $incentiveEntries[] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashIncentiveSums,
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ];

                        $incentiveEntries[] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashIncentiveSums,
                            'acc_no'              => $creditStoreId,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ];
                    }

                    $incentiveEntries[] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => 'Additional Income',
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $cashIncentiveSums,
                        'acc_no'              => $debitStoreId,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ];

                    $incentiveEntries[] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => 'Additional Income',
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $cashIncentiveSums,
                        'acc_no'              => $payrollBranch->expense_credit,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ];

                    $entries = array_merge($entries, $incentiveEntries);
                }
            } else { 

                //branch - grosspay (bank)
                if ($payrollEmployeeBankSums['grosspay'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->expense_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                //branch - employee statutories (bank)
                if ($payrollEmployeeBankSums['eeSSS'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $eeSSSDebit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['eeSSS'],
                            'acc_no'              => $eeSSSCredit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeBankSums['eePh'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $eePhilHealthDebit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePh'],
                            'acc_no'              => $eePhilHealthCredit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeBankSums['eePagibig'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $eePagibigDebit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['eePagibig'],
                            'acc_no'              => $eePagibigCredit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                //branch - deductions (bank)
                if ($bankTotalAmortization > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $bankDeductionSums->deduction->amortization_debit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $bankDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $bankDeductionSums->ven_name,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $bankDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $bankDeductionSums->ven_name,
                            'amount'              => $bankTotalAmortization,
                            'acc_no'              => $bankDeductionSums->deduction->amortization_credit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - with_holding_tax (bank)
                if ($payrollEmployeeBankSums['withHoldingTax'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxDebit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxCredit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - employer statutories (bank)
                if ($payrollEmployeeBankSums['erSSS'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'ven_name'            => $erSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'ven_name'            => $erSSSVendorName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $erSSSCredit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $erSSSDebit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erSSS'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                if ($payrollEmployeeBankSums['erPh'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'ven_name'            => $erPhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'ven_name'            => $erPhilHealthVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $erPhilHealthCredit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $erPhilHealthDebit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPh'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                if ($payrollEmployeeBankSums['erPagibig'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'ven_name'            => $erPagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'ven_name'            => $erPagibigVendorName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $erPagibigCredit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $erPagibigDebit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeBankSums['erPagibig'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - net taxable adjustment (bank)
                if ($payrollEmployeeBankSums['netTaxableAdjustment'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $adjustmentVendor,
                            'ven_name'            => $adjustmentVendorName,
                            'amount'              => $payrollEmployeeBankSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentDebit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchHOShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $adjustmentVendor,
                            'ven_name'            => $adjustmentVendorName,
                            'amount'              => $payrollEmployeeBankSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentCredit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - incentives amount (bank)
                if ($bankIncentiveSums > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $creditStoreId,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $debitStoreId,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $bankAdditionalAndIncentiveSums,
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - grosspay (cash)
                if ($payrollEmployeeCashSums['grosspay'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $payrollBranch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $payrollBranch->expense_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ]
                    ]);
                }

                //branch - employee statutories (cash)
                if ($payrollEmployeeCashSums['eeSSS'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $eeSSSDebit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eeSSSVendor,
                            'ven_name'            => $eeSSSVendorName,
                            'amount'              => $payrollEmployeeCashSums['eeSSS'],
                            'acc_no'              => $eeSSSCredit,
                            'description_per_acc' => 'Employee SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeCashSums['eePh'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $eePhilHealthDebit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePhilHealthVendor,
                            'ven_name'            => $eePhilHealthVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePh'],
                            'acc_no'              => $eePhilHealthCredit,
                            'description_per_acc' => 'Employee Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeCashSums['eePagibig'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $eePagibigDebit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $eePagibigVendor,
                            'ven_name'            => $eePagibigVendorName,
                            'amount'              => $payrollEmployeeCashSums['eePagibig'],
                            'acc_no'              => $eePagibigCredit,
                            'description_per_acc' => 'Employee Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                //branch - deduction (cash)
                if ($cashTotalAmortization > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $cashDeductionSums->deduction->amortization_debit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $cashDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $cashDeductionSums->ven_name,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $cashDeductionSums->deduction->amortization_vendor_id,
                            'ven_name'            => $cashDeductionSums->ven_name,
                            'amount'              => $cashTotalAmortization,
                            'acc_no'              => $cashDeductionSums->deduction->amortization_credit->acc_no,
                            'description_per_acc' => 'Deduction',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - with_holding_tax (cash)
                if ($payrollEmployeeCashSums['withHoldingTax'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxDebit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountB,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $temporaryAccountA,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $withHoldingTaxVendor,
                            'ven_name'            => $withHoldingTaxVendorName,
                            'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                            'acc_no'              => $withHoldingTaxCredit,
                            'description_per_acc' => 'Withholding Tax',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - employer statutories (cash)
                if ($payrollEmployeeCashSums['erSSS'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'ven_name'            => $erSSSVendorName,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'ven_name'            => $erSSSVendorName,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $erSSSCredit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $erSSSDebit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'apsys_entry_id'      => $secondApsysEntry,
                            'apsys_entry_main_id' => $secondApsysEntryMain,
                            'document_no'         => $start_date . '-' . $end_date,
                            'branch_id'           => $branchId,
                            'branch_name'         => $branchName,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'ven_name'            => $vendorHOName,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $payrollBranch->expense_credit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                if ($payrollEmployeeCashSums['erPh'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $erPhEntries = [];

                    if ($payrollBranch->branch_number != "SFI00001") {
                        $erPhEntries [] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'ven_name'            => $erPhilHealthVendorName,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ];

                        $erPhEntries [] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'ven_name'            => $erPhilHealthVendorName,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $erPhilHealthCredit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ];
                    }

                    $erPhEntries [] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => $start_date . '-' . $end_date,
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $payrollEmployeeCashSums['erPh'],
                        'acc_no'              => $erPhilHealthDebit,
                        'description_per_acc' => 'Employer Philhealth Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ];

                    $erPhEntries [] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => $start_date . '-' . $end_date,
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $payrollEmployeeCashSums['erPh'],
                        'acc_no'              => $payrollBranch->expense_credit,
                        'description_per_acc' => 'Employer Philhealth Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ];
                    
                    $entries = array_merge($entries, $erPhEntries);
                }

                if ($payrollEmployeeCashSums['erPagibig'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $erPagibigEntries = [];

                    if ($payrollBranch->branch_number != "SFI00001") {
        
                        $erPagibigEntries [] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'ven_name'            => $erPagibigVendorName,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ];

                        $erPagibigEntries [] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'ven_name'            => $erPagibigVendorName,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $erPagibigCredit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ];
                    }

                    $erPagibigEntries [] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => $start_date . '-' . $end_date,
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $payrollEmployeeCashSums['erPagibig'],
                        'acc_no'              => $erPagibigDebit,
                        'description_per_acc' => 'Employer Pag-Ibig Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ];

                    $erPagibigEntries [] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => $start_date . '-' . $end_date,
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $payrollEmployeeCashSums['erPagibig'],
                        'acc_no'              => $payrollBranch->expense_credit,
                        'description_per_acc' => 'Employer Pag-Ibig Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ];

                    $entries = array_merge($entries, $erPagibigEntries);
                }

                //branch - net taxable adjustment (cash)
                if ($payrollEmployeeCashSums['netTaxableAdjustment'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentDebit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => $branchShortName . '|' . $start_date . '-' . $end_date,
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $payrollEmployeeCashSums['netTaxableAdjustment'],
                            'acc_no'              => $adjustmentCredit,
                            'description_per_acc' => 'Non-Taxable Adjustment',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }

                //branch - incentives amount (cash)
                if ($cashIncentiveSums > 0) {
                    $firstEntry = $this->generateEntryId($this->counterEntry);
                    $secondEntry = $this->generateEntryId($this->counterEntry);

                    $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
                    $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);

                    $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
                    $incentiveEntries = [];

                    if ($payrollBranch->branch_number != "SFI00001") {

                        $incentiveEntries[] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashIncentiveSums,
                            'acc_no'              => $payrollBranch->advance_debit,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ];

                        $incentiveEntries[] = [
                            'entry_id'            => $firstEntry,
                            'apsys_entry_id'      => $firstApsysEntry,
                            'apsys_entry_main_id' => $firstApsysEntryMain,
                            'document_no'         => 'Additional Income',
                            'branch_id'           => $branchHOId,
                            'branch_name'         => $branchHOName,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'ven_name'            => $payrollEmployeeCashVenName,
                            'amount'              => $cashIncentiveSums,
                            'acc_no'              => $creditStoreId,
                            'description_per_acc' => 'Payroll Incentive',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ];
                    }

                    $incentiveEntries[] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => 'Additional Income',
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $cashIncentiveSums,
                        'acc_no'              => $debitStoreId,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ];

                    $incentiveEntries[] = [
                        'entry_id'            => $secondEntry,
                        'apsys_entry_id'      => $secondApsysEntry,
                        'apsys_entry_main_id' => $secondApsysEntryMain,
                        'document_no'         => 'Additional Income',
                        'branch_id'           => $branchId,
                        'branch_name'         => $branchName,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'ven_name'            => $vendorHOName,
                        'amount'              => $cashIncentiveSums,
                        'acc_no'              => $payrollBranch->expense_credit,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ];

                    $entries = array_merge($entries, $incentiveEntries);
                }
            }

            try {
                DB::beginTransaction();

                foreach ($entries as $entry) {

                    Entry::create([
                        'entry_id'                   => $entry['entry_id'],
                        'pay_date'                   => $this->pay_date,
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
                        'ent_post_date'     => now(),
                        'ven_no'            => $entry['ven_no'],
                        'ent_doc_date'      => now(),
                        'branch_id'         => $entry['branch_number'],
                        'ent_doc_no'        => $entry['document_no'],
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
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                $this->notifyJobFailed();
                Log::error('Failed to create entry', [
                    'entry' => $entry,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
