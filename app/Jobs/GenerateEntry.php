<?php

namespace App\Jobs;

use App\Models\Accounting\Account;
use App\Models\Accounting\Vendor;
use App\Models\AdditionalIncome;
use App\Models\Branch;
use App\Models\ContributionAndTax;
use App\Models\Deduction;
use App\Models\EmployeeDeduction;
use App\Models\Entry;
use App\Models\Incentives;
use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Log;

class GenerateEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $payroll;
    protected $user;
    private $counter;
    /**
     * Create a new job instance.
     */
    public function __construct(Collection $payroll, $user)
    {
        $this->payroll = $payroll;
        $this->user = $user;
    }

    private function generateEntryId(&$counter)
    {
        return 'PSD' . str_pad($counter++, 8, '0', STR_PAD_LEFT);
    }

    public function handle(): void
    {
        
        $this->counter = 1;

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

            if ($vendorName === 'EESSS') {
                $eeSSSVendor = $record->vendor_id;
                $eeSSSDebit  = $record->debit_id;
                $eeSSSCredit = $record->credit_id;
            }

            if ($vendorName === 'EEPH') {
                $eePhilHealthVendor = $record->vendor_id;
                $eePhilHealthDebit  = $record->debit_id;
                $eePhilHealthCredit = $record->credit_id;
            }

            if ($vendorName === 'EEPG') {
                $eePagibigVendor = $record->vendor_id;
                $eePagibigDebit  = $record->debit_id;
                $eePagibigCredit = $record->credit_id;
            }

            if ($vendorName === 'ERSSS') {
                $erSSSVendor = $record->vendor_id;
                $erSSSDebit  = $record->debit_id;
                $erSSSCredit = $record->credit_id;
            }

            if ($vendorName === 'ERPH') {
                $erPhilHealthVendor = $record->vendor_id;
                $erPhilHealthDebit  = $record->debit_id;
                $erPhilHealthCredit = $record->credit_id;
            }

            if ($vendorName === 'ERPG') {
                $erPagibigVendor = $record->vendor_id;
                $erPagibigDebit  = $record->debit_id;
                $erPagibigCredit = $record->credit_id;
            }

            if ($vendorName === 'WTAX') {
                $withHoldingTaxVendor = $record->vendor_id;
                $withHoldingTaxDebit  = $record->debit_id;
                $withHoldingTaxCredit = $record->credit_id;
            }

            if ($vendorName === 'ADJUSTMENT') {
                $adjustmentVendor = $record->vendor_id;
                $adjustmentDebit  = $record->debit_id;
                $adjustmentCredit = $record->credit_id;
            }
        }

        $vendor = Vendor::where("ven_name", "HO")->first();
        $vendorHONo = $vendor->ven_no;

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

        $debitHOId = $additionalIncome->head_office_debit_id;
        $creditHOId = $additionalIncome->head_office_credit_id;
        $debitStoreId = $additionalIncome->store_debit_id;
        $creditStoreId = $additionalIncome->store_credit_id;

        $employee_id = $this->user->employee->id;

        $branch = Branch::with(['expense_debit', 'expense_credit', 'advance_debit', 'advance_credit'])
            ->first();

        $branchHO = Branch::where('short_name', 'HO')
            ->first();
        $branchHOId = $branchHO->id;
        $branchHONumber = $branchHO->branch_number;

        $payrollBranchIds = Payroll::distinct()
            ->pluck('timekeeping_branch_id');

        foreach ($payrollBranchIds as $payrollBranchId) {
            $payrollBranch = Branch::find($payrollBranchId);

            $branchId = $payrollBranch->id;
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

                        $monthlyAmortization = floatval(str_replace(',', '', $bankDeductionJson['monthly_amortization'] ?? 0));

                        $bankTotalMonthlyAmortization += $monthlyAmortization;

                        $employeeBankDeduction = EmployeeDeduction::find($bankDeductionId);
                        $employeeBankDeductionId = $employeeBankDeduction->deduction_id;

                        $bankDeductionModel = Deduction::with([
                            'amortization_debit',
                            'amortization_credit',
                            'amortization_vendor'
                        ])->find($employeeBankDeductionId);

                        $bankDeductionSum->push((object)[
                            'employee_id' => $employeeId,
                            'deduction_id' => $bankDeductionId,
                            'deduction' => $bankDeductionModel,
                        ]);
                    }
                }
            }

            $bankDeductionSums = $bankDeductionSum->first();
            $bankTotalAmortization = $bankTotalMonthlyAmortization;

            $bankEmployeeIds = $payrollEmployeeBankQuery->pluck('employee_id');
            $bankPayDate = $payrollEmployeeBankQuery->pluck('pay_date')->unique()->first();
            $bankIncentiveSums = Incentives::whereIn('employee_id', $bankEmployeeIds)
                ->where('pay_date', $bankPayDate) 
                ->sum('incentive_amount');

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

                        $monthlyAmortization = floatval(str_replace(',', '', $cashDeductionJson['monthly_amortization'] ?? 0));

                        $cashTotalMonthlyAmortization += $monthlyAmortization;

                        $employeeCashDeduction = EmployeeDeduction::find($cashDeductionId);
                        $employeeCashDeductionId = $employeeCashDeduction->deduction_id;

                        $cashDeductionModel = Deduction::with([
                            'amortization_debit',
                            'amortization_credit',
                            'amortization_vendor'
                        ])->find($employeeCashDeductionId);

                        $cashDeductionSum->push((object)[
                            'employee_id' => $employeeId,
                            'deduction_id' => $cashDeductionId,
                            'deduction' => $cashDeductionModel,
                        ]);
                    }
                }
            }

            $cashDeductionSums = $cashDeductionSum->first();
            $cashTotalAmortization = $cashTotalMonthlyAmortization;

            $cashEmployeeIds = $payrollEmployeeCashQuery->pluck('employee_id');
            $cashPayDate = $payrollEmployeeCashQuery->pluck('pay_date')->unique()->first();
            $cashIncentiveSums = Incentives::whereIn('employee_id', $cashEmployeeIds)
                ->where('pay_date', $cashPayDate) 
                ->sum('incentive_amount');

            $entries = [];

            // grosspay (cash and bank)
            if ($payrollBranch->type === "Head Office") {
                if ($payrollEmployeeBankSums['grosspay'] > 0 && $payrollBranch->branch_number != "SFI00001") {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->expense_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->expense_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeCashSums['grosspay'] > 0 && $payrollBranch->branch_number != "SFI00001") {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->expense_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->expense_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ]
                    ]);
                }
            } else { 
                if ($payrollEmployeeBankSums['grosspay'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->expense_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeBankSums['grosspay'],
                            'acc_no'              => $branch->expense_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                if ($payrollEmployeeCashSums['grosspay'] > 0) {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->advance_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->advance_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->expense_debit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ],
                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                            'amount'              => $payrollEmployeeCashSums['grosspay'],
                            'acc_no'              => $branch->expense_credit,
                            'description_per_acc' => 'Grosspay',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0
                        ]
                    ]);
                }
            }

            //employee statutories (bank)
            if ($payrollEmployeeBankSums['eeSSS'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['eeSSS'],
                        'acc_no'              => $eeSSSDebit,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['eeSSS'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eeSSSVendor,
                        'amount'              => $payrollEmployeeBankSums['eeSSS'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eeSSSVendor,
                        'amount'              => $payrollEmployeeBankSums['eeSSS'],
                        'acc_no'              => $eeSSSCredit,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            }

            if ($payrollEmployeeBankSums['eePh'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['eePh'],
                        'acc_no'              => $eePhilHealthDebit,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['eePh'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePhilHealthVendor,
                        'amount'              => $payrollEmployeeBankSums['eePh'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePhilHealthVendor,
                        'amount'              => $payrollEmployeeBankSums['eePh'],
                        'acc_no'              => $eePhilHealthCredit,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            }

            if ($payrollEmployeeBankSums['eePagibig'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['eePagibig'],
                        'acc_no'              => $eePagibigDebit,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['eePagibig'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePagibigVendor,
                        'amount'              => $payrollEmployeeBankSums['eePagibig'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePagibigVendor,
                        'amount'              => $payrollEmployeeBankSums['eePagibig'],
                        'acc_no'              => $eePagibigCredit,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            }

            // deductions (bank)
            if ($bankTotalAmortization > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $bankTotalAmortization,
                        'acc_no'              => $bankDeductionSums->deduction->amortization_debit->acc_no,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $bankTotalAmortization,
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $bankDeductionSums->deduction->amortization_vendor_id,
                        'amount'              => $bankTotalAmortization,
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $bankDeductionSums->deduction->amortization_vendor_id,
                        'amount'              => $bankTotalAmortization,
                        'acc_no'              => $bankDeductionSums->deduction->amortization_credit->acc_no,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            // with_holding_tax (bank)
            if ($payrollEmployeeBankSums['withHoldingTax'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                        'acc_no'              => $withHoldingTaxDebit,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $withHoldingTaxVendor,
                        'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $withHoldingTaxVendor,
                        'amount'              => $payrollEmployeeBankSums['withHoldingTax'],
                        'acc_no'              => $withHoldingTaxCredit,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            // employer statutories (bank)
            if ($payrollEmployeeBankSums['erSSS'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $erSSSVendor,
                        'amount'              => $payrollEmployeeBankSums['erSSS'],
                        'acc_no'              => $branch->advance_debit,
                        'description_per_acc' => 'Employer SSS Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $erSSSVendor,
                        'amount'              => $payrollEmployeeBankSums['erSSS'],
                        'acc_no'              => $erSSSCredit,
                        'description_per_acc' => 'Employer SSS Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['erSSS'],
                        'acc_no'              => $erSSSDebit,
                        'description_per_acc' => 'Employer SSS Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['erSSS'],
                        'acc_no'              => $branch->expense_credit,
                        'description_per_acc' => 'Employer SSS Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            if ($payrollEmployeeBankSums['erPh'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $erPhilHealthVendor,
                        'amount'              => $payrollEmployeeBankSums['erPh'],
                        'acc_no'              => $branch->advance_debit,
                        'description_per_acc' => 'Employer Philhealth Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $erPhilHealthVendor,
                        'amount'              => $payrollEmployeeBankSums['erPh'],
                        'acc_no'              => $erPhilHealthCredit,
                        'description_per_acc' => 'Employer Philhealth Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['erPh'],
                        'acc_no'              => $erPhilHealthDebit,
                        'description_per_acc' => 'Employer Philhealth Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['erPh'],
                        'acc_no'              => $branch->expense_credit,
                        'description_per_acc' => 'Employer Philhealth Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            if ($payrollEmployeeBankSums['erPagibig'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $erPagibigVendor,
                        'amount'              => $payrollEmployeeBankSums['erPagibig'],
                        'acc_no'              => $branch->advance_debit,
                        'description_per_acc' => 'Employer Pag-Ibig Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $erPagibigVendor,
                        'amount'              => $payrollEmployeeBankSums['erPagibig'],
                        'acc_no'              => $erPagibigCredit,
                        'description_per_acc' => 'Employer Pag-Ibig Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['erPagibig'],
                        'acc_no'              => $erPagibigDebit,
                        'description_per_acc' => 'Employer Pag-Ibig Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $payrollEmployeeBankSums['erPagibig'],
                        'acc_no'              => $erPagibigCredit,
                        'description_per_acc' => 'Employer Pag-Ibig Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            //net taxable adjustment (bank)
            if ($payrollEmployeeBankSums['netTaxableAdjustment'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $adjustmentVendor,
                        'amount'              => $payrollEmployeeBankSums['netTaxableAdjustment'],
                        'acc_no'              => $adjustmentDebit,
                        'description_per_acc' => 'Non-Taxable Adjustment',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $adjustmentVendor,
                        'amount'              => $payrollEmployeeBankSums['netTaxableAdjustment'],
                        'acc_no'              => $adjustmentCredit,
                        'description_per_acc' => 'Non-Taxable Adjustment',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            //incentives amount (bank)
            if ($bankIncentiveSums > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $bankIncentiveSums,
                        'acc_no'              => $branch->advance_debit,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $bankIncentiveSums,
                        'acc_no'              => $creditStoreId,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $bankIncentiveSums,
                        'acc_no'              => $debitStoreId,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchId,
                        'branch_number'       => $branchNumber,
                        'ven_no'              => $vendorHONo,
                        'amount'              => $bankIncentiveSums,
                        'acc_no'              => $branch->expense_credit,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            //employee statutories (cash)
            if ($payrollEmployeeCashSums['eeSSS'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['eeSSS'],
                        'acc_no'              => $eeSSSDebit,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['eeSSS'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eeSSSVendor,
                        'amount'              => $payrollEmployeeCashSums['eeSSS'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eeSSSVendor,
                        'amount'              => $payrollEmployeeCashSums['eeSSS'],
                        'acc_no'              => $eeSSSCredit,
                        'description_per_acc' => 'Employee SSS Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            }

            if ($payrollEmployeeCashSums['eePh'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['eePh'],
                        'acc_no'              => $eePhilHealthDebit,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['eePh'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePhilHealthVendor,
                        'amount'              => $payrollEmployeeCashSums['eePh'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePhilHealthVendor,
                        'amount'              => $payrollEmployeeCashSums['eePh'],
                        'acc_no'              => $eePhilHealthCredit,
                        'description_per_acc' => 'Employee Philhealth Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            }

            if ($payrollEmployeeCashSums['eePagibig'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['eePagibig'],
                        'acc_no'              => $eePagibigDebit,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['eePagibig'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePagibigVendor,
                        'amount'              => $payrollEmployeeCashSums['eePagibig'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],
                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $eePagibigVendor,
                        'amount'              => $payrollEmployeeCashSums['eePagibig'],
                        'acc_no'              => $eePagibigCredit,
                        'description_per_acc' => 'Employee Pag-Ibig Contribution',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ]
                ]);
            }

            //deduction (cash)
            if ($cashTotalAmortization > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $cashTotalAmortization,
                        'acc_no'              => $cashDeductionSums->deduction->amortization_debit->acc_no,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $cashTotalAmortization,
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $cashDeductionSums->deduction->amortization_vendor_id,
                        'amount'              => $cashTotalAmortization,
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $cashDeductionSums->deduction->amortization_vendor_id,
                        'amount'              => $cashTotalAmortization,
                        'acc_no'              => $cashDeductionSums->deduction->amortization_credit->acc_no,
                        'description_per_acc' => 'Deduction',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            // with_holding_tax (cash)
            if ($payrollEmployeeCashSums['withHoldingTax'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                        'acc_no'              => $withHoldingTaxDebit,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                        'acc_no'              => $temporaryAccountB,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $withHoldingTaxVendor,
                        'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                        'acc_no'              => $temporaryAccountA,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $secondEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $withHoldingTaxVendor,
                        'amount'              => $payrollEmployeeCashSums['withHoldingTax'],
                        'acc_no'              => $withHoldingTaxCredit,
                        'description_per_acc' => 'Withholding Tax',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            if ($payrollEmployeeCashSums['erSSS'] > 0) {
                if ($payrollBranch->branch_number != "SFI00001") {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $branch->advance_debit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erSSSVendor,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $erSSSCredit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $erSSSDebit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $branch->expense_credit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                        ],
                    ]);
                } else {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $erSSSDebit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $accountsPayable,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $erSSSVendor,
                            'amount'              => $payrollEmployeeCashSums['erSSS'],
                            'acc_no'              => $erSSSDebit,
                            'description_per_acc' => 'Employer SSS Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $erSSSVendor,
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
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $branch->advance_debit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $erPhilHealthCredit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $erPhilHealthDebit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $branch->expense_credit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $isPayrollEmployeeCashOnHold ? 1 : 0,
                        ],
                    ]);
                } else {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $erPhilHealthDebit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $accountsPayable,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $erPhilHealthDebit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $erPhilHealthVendor,
                            'amount'              => $payrollEmployeeCashSums['erPh'],
                            'acc_no'              => $erPhilHealthCredit,
                            'description_per_acc' => 'Employer Philhealth Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }
            }

            if ($payrollEmployeeCashSums['erPagibig'] > 0) {
                if ($payrollBranch->branch_number != "SFI00001") {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $branch->advance_debit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'ven_no'              => $erPagibigVendor,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $erPagibigCredit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $erPagibigDebit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $erPagibigCredit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                } else {
                    $firstEntry = $this->generateEntryId($this->counter);
                    $secondEntry = $this->generateEntryId($this->counter);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $erPagibigDebit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $firstEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $vendorHONo,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $accountsPayable,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $erPagibigVendor,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $erPagibigDebit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
                        ],

                        [
                            'entry_id'            => $secondEntry,
                            'branch_id'           => $branchId,
                            'branch_number'       => $branchNumber,
                            'ven_no'              => $erPagibigVendor,
                            'amount'              => $payrollEmployeeCashSums['erPagibig'],
                            'acc_no'              => $erPagibigCredit,
                            'description_per_acc' => 'Employer Pag-Ibig Contribution',
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ],
                    ]);
                }
            }

            //net taxable adjustment (cash)
            if ($payrollEmployeeCashSums['netTaxableAdjustment'] > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['netTaxableAdjustment'],
                        'acc_no'              => $adjustmentDebit,
                        'description_per_acc' => 'Non-Taxable Adjustment',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ],

                    [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $payrollEmployeeCashSums['netTaxableAdjustment'],
                        'acc_no'              => $adjustmentCredit,
                        'description_per_acc' => 'Non-Taxable Adjustment',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ],
                ]);
            }

            if ($cashIncentiveSums > 0) {
                $firstEntry = $this->generateEntryId($this->counter);
                $secondEntry = $this->generateEntryId($this->counter);
                $incentiveEntries = [];

                if ($payrollBranch->branch_number != "SFI00001") {

                    $incentiveEntries[] = [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $cashIncentiveSums,
                        'acc_no'              => $branch->advance_debit,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 0,
                    ];

                    $incentiveEntries[] = [
                        'entry_id'            => $firstEntry,
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'ven_no'              => $payrollEmployeeCash->employee->vendor->ven_no,
                        'amount'              => $cashIncentiveSums,
                        'acc_no'              => $creditStoreId,
                        'description_per_acc' => 'Payroll Incentive',
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 0,
                    ];
                }

                $incentiveEntries[] = [
                    'entry_id'            => $secondEntry,
                    'branch_id'           => $branchId,
                    'branch_number'       => $branchNumber,
                    'ven_no'              => $vendorHONo,
                    'amount'              => $cashIncentiveSums,
                    'acc_no'              => $debitStoreId,
                    'description_per_acc' => 'Payroll Incentive',
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ];

                $incentiveEntries[] = [
                    'entry_id'            => $secondEntry,
                    'branch_id'           => $branchId,
                    'branch_number'       => $branchNumber,
                    'ven_no'              => $vendorHONo,
                    'amount'              => $cashIncentiveSums,
                    'acc_no'              => $branch->expense_credit,
                    'description_per_acc' => 'Payroll Incentive',
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ];

                $entries = array_merge($entries, $incentiveEntries);
            }

            foreach ($entries as $entry) {
                try {
                    Entry::create([
                        'entry_id'                   => $entry['entry_id'],
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
                } catch (\Exception $e) {
                    Log::error('Failed to create entry', [
                        'entry' => $entry,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
