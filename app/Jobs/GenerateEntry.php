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
    protected $sendToApsys = true;
    protected $apsysAvailable = true;
    private $counterEntry;
    private $counterApsysEntry;
    private $counterApsysEntryMain;
    /**
     * Create a new job instance.
     */
    public function __construct(Collection $payroll, $pay_date, $start_date, $end_date, $user, bool $sendToApsys = true)
    {
        $this->payroll = $payroll;
        $this->pay_date = $pay_date;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->user = $user;
        $this->sendToApsys = $sendToApsys;
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
        if (!$this->apsysAvailable) {
            return null;
        }

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
        if (!$this->apsysAvailable) {
            return null;
        }

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

    private function isApsysAvailable(): bool
    {
        try {
            DB::connection('apsys')->getPdo();
            return true;
        } catch (\Throwable $e) {
            Log::warning('APSYS connection unavailable; generating entries without APSYS.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getAmortizationDebitAccNo($deduction): ?string
    {
        if ($this->apsysAvailable) {
            return $deduction->amortization_debit->acc_no ?? null;
        }

        return $deduction->amortization_debit_id ?? null;
    }

    private function getAmortizationCreditAccNo($deduction): ?string
    {
        if ($this->apsysAvailable) {
            return $deduction->amortization_credit->acc_no ?? null;
        }

        return $deduction->amortization_credit_id ?? null;
    }

    private function parseMoneyValue($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    private function getDeductionAmountFromJson(array $deductionJson): float
    {
        $rawValue = $deductionJson['deducted_amount'] ?? null;
        if ($rawValue === null || $rawValue === '') { 
            $rawValue = $deductionJson['monthly_amortization'] ?? 0;
        }

        return $this->parseMoneyValue($rawValue);
    }

    private function resolveDeductionModelFromJson(array $deductionJson, array $deductionRelations = []): ?Deduction
    {
        $employeeDeductionId = $deductionJson['deduction_id'] ?? null;

        if ($employeeDeductionId) {
            $employeeDeduction = EmployeeDeduction::find($employeeDeductionId);
            if ($employeeDeduction && $employeeDeduction->deduction_id) {
                $query = Deduction::query();
                if (!empty($deductionRelations)) {
                    $query->with($deductionRelations);
                }

                $deduction = $query->find($employeeDeduction->deduction_id);
                if ($deduction) {
                    return $deduction;
                }
            }
        }

        $deductionType = trim((string) ($deductionJson['type'] ?? ''));
        $fallbackQuery = Deduction::query();
        if (!empty($deductionRelations)) {
            $fallbackQuery->with($deductionRelations);
        }

        if ($deductionType !== '') {
            $matchedByType = (clone $fallbackQuery)
                ->where(function ($query) use ($deductionType) {
                    $query->where('description', $deductionType)
                        ->orWhere('payslip_description', $deductionType);
                })
                ->orderByRaw("status = 'active' DESC")
                ->orderBy('id')
                ->first();

            if ($matchedByType) {
                return $matchedByType;
            }
        }

        return (clone $fallbackQuery)
            ->where('description', 'Others')
            ->orderByRaw("status = 'active' DESC")
            ->orderBy('id')
            ->first();
    }

    private function normalizeDeductionCategory(?string $category): string
    {
        $normalized = trim((string) $category);
        if ($normalized === '') {
            return 'uncategorized';
        }

        $normalized = strtolower($normalized);

        if ($normalized === 'pagibig') {
            return 'pag-ibig';
        }

        return $normalized;
    }

    private function getDeductionEntryLabel(Deduction $deduction): string
    {
        return match ($this->normalizeDeductionCategory($deduction->category ?? null)) {
            'company' => 'Company Deductions',
            'sss' => 'SSS Loan',
            'pag-ibig' => 'PAG-IBIG Loan',
            'bank salary loan' => 'Bank Salary Loan',
            'company loan' => 'Company Loan',
            default => $deduction->description ?: 'Deduction',
        };
    }

    private function buildDeductionGroupKey(Deduction $deduction): string
    {
        return implode('|', [
            $this->normalizeDeductionCategory($deduction->category ?? null),
            (string) ($deduction->amortization_vendor_id ?? ''),
            (string) ($deduction->amortization_debit_id ?? ''),
            (string) ($deduction->amortization_credit_id ?? ''),
        ]);
    }

    private function accumulateDeductionGroup(array &$groups, Deduction $deduction, float $amount, ?string $vendorName): void
    {
        if ($amount <= 0) {
            return;
        }

        $groupKey = $this->buildDeductionGroupKey($deduction);

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'deduction' => $deduction,
                'ven_name' => $vendorName,
                'amount' => 0.0,
                'label' => $this->getDeductionEntryLabel($deduction),
            ];
        }

        $groups[$groupKey]['amount'] += $amount;

        if (empty($groups[$groupKey]['ven_name']) && !empty($vendorName)) {
            $groups[$groupKey]['ven_name'] = $vendorName;
        }
    }

    private function buildAmortizationEntries(
        array $deductionGroups,
        string $documentNo,
        int $branchId,
        string $branchName,
        string $branchNumber,
        ?string $sourceVendorNo,
        ?string $sourceVendorName,
        ?string $temporaryAccountA,
        ?string $temporaryAccountB
    ): array {
        $entries = [];

        foreach ($deductionGroups as $group) {
            $deduction = $group['deduction'] ?? null;
            $amount = (float) ($group['amount'] ?? 0);

            if (!$deduction || $amount <= 0) {
                continue;
            }

            $label = $group['label'] ?? 'Deduction';

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
                    'document_no'         => $documentNo,
                    'branch_id'           => $branchId,
                    'branch_name'         => $branchName,
                    'branch_number'       => $branchNumber,
                    'ven_no'              => $sourceVendorNo,
                    'ven_name'            => $sourceVendorName,
                    'amount'              => $amount,
                    'acc_no'              => $this->getAmortizationDebitAccNo($deduction),
                    'description_per_acc' => $label,
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $firstEntry,
                    'apsys_entry_id'      => $firstApsysEntry,
                    'apsys_entry_main_id' => $firstApsysEntryMain,
                    'document_no'         => $documentNo,
                    'branch_id'           => $branchId,
                    'branch_name'         => $branchName,
                    'branch_number'       => $branchNumber,
                    'ven_no'              => $sourceVendorNo,
                    'ven_name'            => $sourceVendorName,
                    'amount'              => $amount,
                    'acc_no'              => $temporaryAccountB,
                    'description_per_acc' => $label,
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $secondEntry,
                    'apsys_entry_id'      => $secondApsysEntry,
                    'apsys_entry_main_id' => $secondApsysEntryMain,
                    'document_no'         => $documentNo,
                    'branch_id'           => $branchId,
                    'branch_name'         => $branchName,
                    'branch_number'       => $branchNumber,
                    'ven_no'              => $deduction->amortization_vendor_id,
                    'ven_name'            => $group['ven_name'] ?? null,
                    'amount'              => $amount,
                    'acc_no'              => $temporaryAccountA,
                    'description_per_acc' => $label,
                    'balance_type'        => 'Debit',
                    'is_block_payment'    => 0,
                ],
                [
                    'entry_id'            => $secondEntry,
                    'apsys_entry_id'      => $secondApsysEntry,
                    'apsys_entry_main_id' => $secondApsysEntryMain,
                    'document_no'         => $documentNo,
                    'branch_id'           => $branchId,
                    'branch_name'         => $branchName,
                    'branch_number'       => $branchNumber,
                    'ven_no'              => $deduction->amortization_vendor_id,
                    'ven_name'            => $group['ven_name'] ?? null,
                    'amount'              => $amount,
                    'acc_no'              => $this->getAmortizationCreditAccNo($deduction),
                    'description_per_acc' => $label,
                    'balance_type'        => 'Credit',
                    'is_block_payment'    => 0,
                ],
            ]);
        }

        return $entries;
    }

    private function resolveCashSourceVendor(
        Payroll $payroll,
        ?string $fallbackVendorNo,
        ?string $fallbackVendorName
    ): array {
        $vendorNo = $fallbackVendorNo;
        $vendorName = $fallbackVendorName;

        if ($payroll->employee) {
            $vendorNo = $payroll->employee->employee_number ?? $vendorNo;
            $vendorName = $payroll->employee->full_name ?? $vendorName;
        }

        return [$vendorNo, $vendorName];
    }

    private function resolveHeadOfficeBranch(): Branch
    {
        $headOfficeBranch = Branch::query()
            ->where(function ($query) {
                $query->where('short_name', 'HO')
                    ->orWhere('name', 'Head Office');
            })
            ->orderByRaw("CASE WHEN short_name = 'HO' THEN 0 ELSE 1 END")
            ->first();

        if (!$headOfficeBranch) {
            throw new \RuntimeException(
                'Unable to generate entries. Head Office branch record is missing.'
            );
        }

        return $headOfficeBranch;
    }

    private function scopePayrollToEffectiveBranch($query, int $branchId, int $branchHOId)
    {
        return $query->where(function ($branchQuery) use ($branchId, $branchHOId) {
            if ($branchId === $branchHOId) {
                $branchQuery->where(function ($headOfficeQuery) use ($branchId) {
                    $headOfficeQuery->where('timekeeping_branch_id', $branchId)
                        ->where('is_maternity', 0);
                })->orWhere('is_maternity', 1);

                return;
            }

            $branchQuery->where('timekeeping_branch_id', $branchId)
                ->where('is_maternity', 0);
        });
    }

    private function appendCashPayrollPayableEntries(
        array &$entries,
        Payroll $payroll,
        string $firstDocumentNo,
        int $firstBranchId,
        string $firstBranchName,
        string $firstBranchNumber,
        string $secondDocumentNo,
        int $secondBranchId,
        string $secondBranchName,
        string $secondBranchNumber,
        ?string $fallbackSourceVendorNo,
        ?string $fallbackSourceVendorName,
        ?string $vendorHONo,
        ?string $vendorHOName,
        object $payrollBranch,
        int $isBlockPayment,
        bool $includeAdvanceEntry = true
    ): void {
        $amount = $this->parseMoneyValue($payroll->net_pay);
        if ($amount <= 0) {
            return;
        }

        [$sourceVendorNo, $sourceVendorName] = $this->resolveCashSourceVendor(
            $payroll,
            $fallbackSourceVendorNo,
            $fallbackSourceVendorName
        );

        $firstEntry = $this->generateEntryId($this->counterEntry);
        $secondEntry = $this->generateEntryId($this->counterEntry);
        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);
        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);

        if ($includeAdvanceEntry) {
            $entries[] = [
                'entry_id'            => $firstEntry,
                'apsys_entry_id'      => $firstApsysEntry,
                'apsys_entry_main_id' => $firstApsysEntryMain,
                'document_no'         => $firstDocumentNo,
                'branch_id'           => $firstBranchId,
                'branch_name'         => $firstBranchName,
                'branch_number'       => $firstBranchNumber,
                'ven_no'              => $sourceVendorNo,
                'ven_name'            => $sourceVendorName,
                'amount'              => $amount,
                'acc_no'              => $payrollBranch->advance_debit,
                'description_per_acc' => 'Grosspay',
                'balance_type'        => 'Debit',
                'is_block_payment'    => $isBlockPayment,
            ];
            $entries[] = [
                'entry_id'            => $firstEntry,
                'apsys_entry_id'      => $firstApsysEntry,
                'apsys_entry_main_id' => $firstApsysEntryMain,
                'document_no'         => $firstDocumentNo,
                'branch_id'           => $firstBranchId,
                'branch_name'         => $firstBranchName,
                'branch_number'       => $firstBranchNumber,
                'ven_no'              => $sourceVendorNo,
                'ven_name'            => $sourceVendorName,
                'amount'              => $amount,
                'acc_no'              => $payrollBranch->advance_credit,
                'description_per_acc' => 'Grosspay',
                'balance_type'        => 'Credit',
                'is_block_payment'    => $isBlockPayment,
            ];
        }

        $entries[] = [
            'entry_id'            => $secondEntry,
            'apsys_entry_id'      => $secondApsysEntry,
            'apsys_entry_main_id' => $secondApsysEntryMain,
            'document_no'         => $secondDocumentNo,
            'branch_id'           => $secondBranchId,
            'branch_name'         => $secondBranchName,
            'branch_number'       => $secondBranchNumber,
            'ven_no'              => $vendorHONo,
            'ven_name'            => $vendorHOName,
            'amount'              => $amount,
            'acc_no'              => $payrollBranch->expense_debit,
            'description_per_acc' => 'Grosspay',
            'balance_type'        => 'Debit',
            'is_block_payment'    => $isBlockPayment,
        ];
        $entries[] = [
            'entry_id'            => $secondEntry,
            'apsys_entry_id'      => $secondApsysEntry,
            'apsys_entry_main_id' => $secondApsysEntryMain,
            'document_no'         => $secondDocumentNo,
            'branch_id'           => $secondBranchId,
            'branch_name'         => $secondBranchName,
            'branch_number'       => $secondBranchNumber,
            'ven_no'              => $vendorHONo,
            'ven_name'            => $vendorHOName,
            'amount'              => $amount,
            'acc_no'              => $payrollBranch->expense_credit,
            'description_per_acc' => 'Grosspay',
            'balance_type'        => 'Credit',
            'is_block_payment'    => $isBlockPayment,
        ];
    }

    private function appendCashContributionEntries(
        array &$entries,
        Payroll $payroll,
        float $amount,
        string $description,
        ?string $debitAcc,
        ?string $creditVendorNo,
        ?string $creditVendorName,
        ?string $creditAcc,
        string $documentNo,
        int $branchId,
        string $branchName,
        string $branchNumber,
        ?string $fallbackSourceVendorNo,
        ?string $fallbackSourceVendorName,
        ?string $temporaryAccountA,
        ?string $temporaryAccountB
    ): void {
        if ($amount <= 0) {
            return;
        }

        [$sourceVendorNo, $sourceVendorName] = $this->resolveCashSourceVendor(
            $payroll,
            $fallbackSourceVendorNo,
            $fallbackSourceVendorName
        );

        $firstEntry = $this->generateEntryId($this->counterEntry);
        $secondEntry = $this->generateEntryId($this->counterEntry);
        $firstApsysEntry = $this->generateEntryId($this->counterApsysEntry);
        $secondApsysEntry = $this->generateEntryId($this->counterApsysEntry);
        $firstApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);
        $secondApsysEntryMain = $this->generateEntryId($this->counterApsysEntryMain);

        $entries[] = [
            'entry_id'            => $firstEntry,
            'apsys_entry_id'      => $firstApsysEntry,
            'apsys_entry_main_id' => $firstApsysEntryMain,
            'document_no'         => $documentNo,
            'branch_id'           => $branchId,
            'branch_name'         => $branchName,
            'branch_number'       => $branchNumber,
            'ven_no'              => $sourceVendorNo,
            'ven_name'            => $sourceVendorName,
            'amount'              => $amount,
            'acc_no'              => $debitAcc,
            'description_per_acc' => $description,
            'balance_type'        => 'Debit',
            'is_block_payment'    => 0,
        ];
        $entries[] = [
            'entry_id'            => $firstEntry,
            'apsys_entry_id'      => $firstApsysEntry,
            'apsys_entry_main_id' => $firstApsysEntryMain,
            'document_no'         => $documentNo,
            'branch_id'           => $branchId,
            'branch_name'         => $branchName,
            'branch_number'       => $branchNumber,
            'ven_no'              => $sourceVendorNo,
            'ven_name'            => $sourceVendorName,
            'amount'              => $amount,
            'acc_no'              => $temporaryAccountB,
            'description_per_acc' => $description,
            'balance_type'        => 'Credit',
            'is_block_payment'    => 0,
        ];
        $entries[] = [
            'entry_id'            => $secondEntry,
            'apsys_entry_id'      => $secondApsysEntry,
            'apsys_entry_main_id' => $secondApsysEntryMain,
            'document_no'         => $documentNo,
            'branch_id'           => $branchId,
            'branch_name'         => $branchName,
            'branch_number'       => $branchNumber,
            'ven_no'              => $creditVendorNo,
            'ven_name'            => $creditVendorName,
            'amount'              => $amount,
            'acc_no'              => $temporaryAccountA,
            'description_per_acc' => $description,
            'balance_type'        => 'Debit',
            'is_block_payment'    => 0,
        ];
        $entries[] = [
            'entry_id'            => $secondEntry,
            'apsys_entry_id'      => $secondApsysEntry,
            'apsys_entry_main_id' => $secondApsysEntryMain,
            'document_no'         => $documentNo,
            'branch_id'           => $branchId,
            'branch_name'         => $branchName,
            'branch_number'       => $branchNumber,
            'ven_no'              => $creditVendorNo,
            'ven_name'            => $creditVendorName,
            'amount'              => $amount,
            'acc_no'              => $creditAcc,
            'description_per_acc' => $description,
            'balance_type'        => 'Credit',
            'is_block_payment'    => 0,
        ];
    }

    private function buildCashDeductionGroupsForPayroll(Payroll $payroll): array
    {
        $groups = [];
        if ($this->parseMoneyValue($payroll->total_deductions) <= 0) {
            return $groups;
        }

        $deductions = json_decode($payroll->deductions, true);
        if (!$deductions) {
            return $groups;
        }

        foreach ($deductions as $deductionJson) {
            $deductionAmount = $this->getDeductionAmountFromJson($deductionJson);
            $deductionRelations = $this->apsysAvailable
                ? ['amortization_debit', 'amortization_credit', 'amortization_vendor']
                : [];
            $deductionModel = $this->resolveDeductionModelFromJson($deductionJson, $deductionRelations);
            if (!$deductionModel) {
                continue;
            }

            $vendorName = null;
            if ($this->apsysAvailable) {
                $vendor = Vendor::where('ven_no', $deductionModel->amortization_vendor_id)->first();
                $vendorName = $vendor->ven_name ?? null;
            }

            $this->accumulateDeductionGroup(
                $groups,
                $deductionModel,
                $deductionAmount,
                $vendorName
            );
        }

        return array_values($groups);
    }

    public function handle(): void
    {
        $this->apsysAvailable = $this->sendToApsys && $this->isApsysAvailable();

        $eeSSSVendor = null;
        $eeSSSDebit = null;
        $eeSSSCredit = null;
        $eeSSSVendorName = null;
        $eePhilHealthVendor = null;
        $eePhilHealthDebit = null;
        $eePhilHealthCredit = null;
        $eePhilHealthVendorName = null;
        $eePagibigVendor = null;
        $eePagibigDebit = null;
        $eePagibigCredit = null;
        $eePagibigVendorName = null;
        $erSSSVendor = null;
        $erSSSDebit = null;
        $erSSSCredit = null;
        $erSSSVendorName = null;
        $erPhilHealthVendor = null;
        $erPhilHealthDebit = null;
        $erPhilHealthCredit = null;
        $erPhilHealthVendorName = null;
        $erPagibigVendor = null;
        $erPagibigDebit = null;
        $erPagibigCredit = null;
        $erPagibigVendorName = null;
        $withHoldingTaxVendor = null;
        $withHoldingTaxDebit = null;
        $withHoldingTaxCredit = null;
        $withHoldingTaxVendorName = null;
        $adjustmentVendor = null;
        $adjustmentDebit = null;
        $adjustmentCredit = null;
        $adjustmentVendorName = null;
        $vendorHONo = null;
        $vendorHOName = null;
        $temporaryAccountA = null;
        $temporaryAccountB = null;
        $accountsPayable = null;
        $accountsPayableName = null;
        
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
                $vendorNameFromDB = null;
                if ($this->apsysAvailable) {
                    $vendor = Vendor::where('ven_no', $record->vendor_id)->first();
                    $vendorNameFromDB = $vendor ? $vendor->ven_name : null;
                }

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

        if ($this->apsysAvailable) {
            $vendor = Vendor::where("ven_name", "HO")->first();
            $vendorHONo = $vendor->ven_no ?? null;
            $vendorHOName = $vendor->ven_name ?? null;
        } else {
            $vendorHONo = "HO";
            $vendorHOName = "HO";
        }

        $accountNames = [
            'TEMPORARY ACCOUNT A',
            'TEMPORARY ACCOUNT B',
            'Accounts Payable',
        ];

        if ($this->apsysAvailable) {
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
        }

        $additionalIncome = AdditionalIncome::where("description", "Incentives")->first();

        $debitStoreId = $additionalIncome->store_debit_id;
        $creditStoreId = $additionalIncome->store_credit_id;

        $userId = $this->user;
        $user = User::find($userId);
        $employee_id = $user->id;

        $branchHO = $this->resolveHeadOfficeBranch();
        $branchHOId = $branchHO->id;
        $branchHOName = $branchHO->name;
        $branchHOShortName = $branchHO->short_name;
        $branchHONumber = $branchHO->branch_number;

        $start_date = $this->start_date;
        $end_date = $this->end_date;
        $cutoffDocumentToken = $start_date . '-' . $end_date;

        $deletedRows = Entry::where('pay_date', $this->pay_date)
            ->where(function ($query) use ($cutoffDocumentToken) {
                $query->where('document_no', 'like', "%{$cutoffDocumentToken}%")
                    ->orWhere('document_no', $cutoffDocumentToken)
                    ->orWhere(function ($incentiveQuery) {
                        $incentiveQuery->where('document_no', 'Additional Income')
                            ->where('description', 'Payroll Incentive');
                    });
            })
            ->delete();

        if ($deletedRows > 0) {
            Log::info('Existing entries removed before regeneration.', [
                'pay_date' => $this->pay_date,
                'cutoff_start' => $start_date,
                'cutoff_end' => $end_date,
                'deleted_rows' => $deletedRows,
            ]);
        }

        $payrollBranchBaseQuery = Payroll::query()
            ->where('pay_date', $this->pay_date)
            ->where('start_date', $start_date)
            ->where('end_date', $end_date);

        $payrollIdsWithoutBranch = (clone $payrollBranchBaseQuery)
            ->where('is_maternity', 0)
            ->where(function ($query) {
                $query->whereNull('timekeeping_branch_id')
                    ->orWhere('timekeeping_branch_id', 0);
            })
            ->pluck('id');

        if ($payrollIdsWithoutBranch->isNotEmpty()) {
            throw new \RuntimeException(
                'Unable to generate entries. Payroll record(s) missing timekeeping branch: ' .
                $payrollIdsWithoutBranch->implode(', ')
            );
        }

        $maternityPayrollIdsWithoutBranch = (clone $payrollBranchBaseQuery)
            ->where('is_maternity', 1)
            ->where(function ($query) {
                $query->whereNull('timekeeping_branch_id')
                    ->orWhere('timekeeping_branch_id', 0);
            })
            ->pluck('id');

        $payrollBranchIds = (clone $payrollBranchBaseQuery)
            ->where('is_maternity', 0)
            ->whereNotNull('timekeeping_branch_id')
            ->where('timekeeping_branch_id', '!=', 0)
            ->distinct()
            ->pluck('timekeeping_branch_id');

        $hasMaternityPayroll = (clone $payrollBranchBaseQuery)
            ->where('is_maternity', 1)
            ->exists();

        if ($hasMaternityPayroll) {
            $payrollBranchIds->push($branchHOId);

            Log::info('Maternity payroll rows are mapped to Head Office for entry generation.', [
                'pay_date' => $this->pay_date,
                'cutoff_start' => $start_date,
                'cutoff_end' => $end_date,
                'payroll_ids_without_branch' => $maternityPayrollIdsWithoutBranch->values()->all(),
                'head_office_branch_id' => $branchHOId,
            ]);
        }

        $payrollBranchIds = $payrollBranchIds->unique()->values();

        $existingBranchIds = Branch::query()
            ->whereIn('id', $payrollBranchIds)
            ->pluck('id');

        $missingBranchIds = $payrollBranchIds->diff($existingBranchIds);

        if ($missingBranchIds->isNotEmpty()) {
            throw new \RuntimeException(
                'Unable to generate entries. Missing branch record(s) for timekeeping_branch_id: ' .
                $missingBranchIds->implode(', ')
            );
        }

        $hasError = false;

        foreach ($payrollBranchIds as $payrollBranchId) {
            $branchRelations = $this->apsysAvailable
                ? ['expense_debit', 'expense_credit', 'advance_debit', 'advance_credit']
                : [];
            $payrollBranch = Branch::with($branchRelations)->find($payrollBranchId);

            $branchId = $payrollBranch->id;
            $branchName = $payrollBranch->name;
            $branchShortName = $payrollBranch->short_name;
            $branchNumber = $payrollBranch->branch_number;

            $payrollEmployeeBankQuery = Payroll::query();
            $this->scopePayrollToEffectiveBranch($payrollEmployeeBankQuery, $branchId, $branchHOId);
            $payrollEmployeeBankQuery = $payrollEmployeeBankQuery
                ->where('pay_date', $this->pay_date)
                ->where('start_date', $this->start_date)
                ->where('end_date', $this->end_date)
                ->where('is_maternity', 0)
                ->whereIn('employee_id', function($query) {
                    $query->select('employee_id')
                        ->from('payroll_list')
                        ->where('is_bank', 1);
                })
                ->get();

            $payrollEmployeeBankSums = [
                'grosspay' => 0,
                'netpay' => 0,
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
                $payrollEmployeeBankSums['netpay'] += floatval(str_replace(',', '', $payrollBank->net_pay));
                $payrollEmployeeBankSums['eeSSS'] += floatval(str_replace(',', '', $payrollBank->employee_sss));
                $payrollEmployeeBankSums['erSSS'] += floatval(str_replace(',', '', $payrollBank->employer_sss));
                $payrollEmployeeBankSums['eePh'] += floatval(str_replace(',', '', $payrollBank->employee_philhealth));
                $payrollEmployeeBankSums['erPh'] += floatval(str_replace(',', '', $payrollBank->employer_philhealth));
                $payrollEmployeeBankSums['eePagibig'] += floatval(str_replace(',', '', $payrollBank->employee_pagibig));
                $payrollEmployeeBankSums['erPagibig'] += floatval(str_replace(',', '', $payrollBank->employer_pagibig));
                $payrollEmployeeBankSums['withHoldingTax'] += floatval(str_replace(',', '', $payrollBank->withholding_tax));
                $payrollEmployeeBankSums['netTaxableAdjustment'] += floatval(str_replace(',', '', $payrollBank->non_taxable_adjustment));
            }

            $bankDeductionGroups = [];

            foreach ($payrollEmployeeBankQuery as $payrollBankDeduction) {
                if ($payrollBankDeduction->total_deductions > 0) {
                    $bankDeductions = json_decode($payrollBankDeduction->deductions, true);
                    if (!$bankDeductions) continue;

                    foreach ($bankDeductions as $bankDeductionJson) {

                        $deductionAmount = $this->getDeductionAmountFromJson($bankDeductionJson);

                        $deductionRelations = $this->apsysAvailable
                            ? ['amortization_debit', 'amortization_credit', 'amortization_vendor']
                            : [];
                        $bankDeductionModel = $this->resolveDeductionModelFromJson($bankDeductionJson, $deductionRelations);
                        if (!$bankDeductionModel) {
                            continue;
                        }

                        $bankDeductionSumsVenName = null;
                        if ($this->apsysAvailable) {
                            $vendor = Vendor::where('ven_no', $bankDeductionModel->amortization_vendor_id)->first();
                            $bankDeductionSumsVenName = $vendor->ven_name ?? null;
                        }

                        $this->accumulateDeductionGroup(
                            $bankDeductionGroups,
                            $bankDeductionModel,
                            $deductionAmount,
                            $bankDeductionSumsVenName
                        );
                    }
                }
            }

            $bankDeductionGroups = array_values($bankDeductionGroups);

            $bankEmployeeIds = $payrollEmployeeBankQuery->pluck('employee_id');
            $bankIncentiveSums = Incentives::whereIn('employee_id', $bankEmployeeIds)
                ->where('pay_date', $this->pay_date) 
                ->sum('incentive_amount');

            $bankAdditionalIncomeSums = BranchAdditionalIncome::where('branch_id', $branchId)
                ->where('pay_date', $this->pay_date) 
                ->sum('amount');

            $bankAdditionalAndIncentiveSums = $bankIncentiveSums + $bankAdditionalIncomeSums;

            $isPayrollEmployeeCashOnHoldQuery = Payroll::query();
            $this->scopePayrollToEffectiveBranch($isPayrollEmployeeCashOnHoldQuery, $branchId, $branchHOId);
            $isPayrollEmployeeCashOnHold = $isPayrollEmployeeCashOnHoldQuery
                ->whereIn('employee_id', function($query) {
                    $query->select('employee_id')
                        ->from('payroll_list')
                        ->where('is_bank', 0)
                        ->where('is_hold', 1);
                })
                ->exists() ? 1 : 0;

            $payrollEmployeeCashQuery = Payroll::with(
                $this->apsysAvailable
                    ? ['employee.vendor:ven_no,ven_name', 'employee', 'timekeeping_branch']
                    : ['employee', 'timekeeping_branch']
            );
            $this->scopePayrollToEffectiveBranch($payrollEmployeeCashQuery, $branchId, $branchHOId);
            $payrollEmployeeCashQuery = $payrollEmployeeCashQuery
                ->where('pay_date', $this->pay_date)
                ->where('start_date', $this->start_date)
                ->where('end_date', $this->end_date)
                ->where(function ($query) {
                    $query->whereIn('employee_id', function($subQuery) {
                        $subQuery->select('employee_id')
                            ->from('payroll_list')
                            ->where('is_bank', 0);
                    })
                    ->orWhere('is_maternity', 1);
                })
                ->get();

            $payrollEmployeeCashSums = [
                'grosspay' => 0,
                'netpay' => 0,
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
            $payrollEmployeeCashVenName = $vendorHOName;
            $payrollEmployeeCashVendorNo = $vendorHONo;
            if ($this->apsysAvailable && $payrollEmployeeCash && $payrollEmployeeCash->employee) {
                $payrollEmployeeCashVenName = $payrollEmployeeCash->employee->vendor->ven_name ?? null;
                $payrollEmployeeCashVendorNo = $payrollEmployeeCash->employee->vendor->ven_no ?? null;
            }

            $cashPayrollVendorName = $vendorHOName;
            $cashPayrollVendorNo = $vendorHONo;
            if ($payrollEmployeeCash && $payrollEmployeeCash->employee) {
                $cashPayrollVendorName = $payrollEmployeeCash->employee->full_name ?? $vendorHOName;
                $cashPayrollVendorNo = $payrollEmployeeCash->employee->employee_number ?? $vendorHONo;
            }

            foreach ($payrollEmployeeCashQuery as $payrollCash) {
                $payrollEmployeeCashSums['grosspay'] += floatval(str_replace(',', '', $payrollCash->gross_pay));
                $payrollEmployeeCashSums['netpay'] += floatval(str_replace(',', '', $payrollCash->net_pay));
                $payrollEmployeeCashSums['eeSSS'] += floatval(str_replace(',', '', $payrollCash->employee_sss));
                $payrollEmployeeCashSums['erSSS'] += floatval(str_replace(',', '', $payrollCash->employer_sss));
                $payrollEmployeeCashSums['eePh'] += floatval(str_replace(',', '', $payrollCash->employee_philhealth));
                $payrollEmployeeCashSums['erPh'] += floatval(str_replace(',', '', $payrollCash->employer_philhealth));
                $payrollEmployeeCashSums['eePagibig'] += floatval(str_replace(',', '', $payrollCash->employee_pagibig));
                $payrollEmployeeCashSums['erPagibig'] += floatval(str_replace(',', '', $payrollCash->employer_pagibig));
                $payrollEmployeeCashSums['withHoldingTax'] += floatval(str_replace(',', '', $payrollCash->withholding_tax));
                $payrollEmployeeCashSums['netTaxableAdjustment'] += floatval(str_replace(',', '', $payrollCash->non_taxable_adjustment));
            }

            $cashDeductionGroups = [];

            foreach ($payrollEmployeeCashQuery as $payrollCashDeduction) {
                if ($payrollCashDeduction->total_deductions > 0) {
                    $cashDeductions = json_decode($payrollCashDeduction->deductions, true);
                    if (!$cashDeductions) continue;

                    foreach ($cashDeductions as $cashDeductionJson) {

                        $deductionAmount = $this->getDeductionAmountFromJson($cashDeductionJson);

                        $deductionRelations = $this->apsysAvailable
                            ? ['amortization_debit', 'amortization_credit', 'amortization_vendor']
                            : [];
                        $cashDeductionModel = $this->resolveDeductionModelFromJson($cashDeductionJson, $deductionRelations);
                        if (!$cashDeductionModel) {
                            continue;
                        }

                        $cashDeductionSumsVenName = null;
                        if ($this->apsysAvailable) {
                            $vendor = Vendor::where('ven_no', $cashDeductionModel->amortization_vendor_id)->first();
                            $cashDeductionSumsVenName = $vendor->ven_name ?? null;
                        }

                        $this->accumulateDeductionGroup(
                            $cashDeductionGroups,
                            $cashDeductionModel,
                            $deductionAmount,
                            $cashDeductionSumsVenName
                        );
                    }
                }
            }

            $cashDeductionGroups = array_values($cashDeductionGroups);

            $cashEmployeeIds = $payrollEmployeeCashQuery
                ->where('is_maternity', 0)
                ->pluck('employee_id');
            $cashIncentiveSums = Incentives::whereIn('employee_id', $cashEmployeeIds)
                ->where('pay_date', $this->pay_date) 
                ->sum('incentive_amount');

            $entries = [];

            if ($payrollBranch->type === "Head Office") {
                //head office - payroll payable (bank)
                if ($payrollEmployeeBankSums['netpay'] > 0 && $payrollBranch->branch_number != "SFI00001") {
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                if (!empty($bankDeductionGroups)) {
                    $entries = array_merge(
                        $entries,
                        $this->buildAmortizationEntries(
                            $bankDeductionGroups,
                            $branchShortName . '|' . $start_date . '-' . $end_date,
                            $branchHOId,
                            $branchHOName,
                            $branchHONumber,
                            $vendorHONo,
                            $vendorHOName,
                            $temporaryAccountA,
                            $temporaryAccountB
                        )
                    );
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
                if ($bankAdditionalAndIncentiveSums > 0) {
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

                //head office - payroll payable (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    if ($payrollCash->is_maternity) {
                        continue;
                    }

                    $this->appendCashPayrollPayableEntries(
                        $entries,
                        $payrollCash,
                        $branchShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $start_date . '-' . $end_date,
                        $branchId,
                        $branchName,
                        $branchNumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $vendorHONo,
                        $vendorHOName,
                        $payrollBranch,
                        $isPayrollEmployeeCashOnHold ? 1 : 0,
                        $payrollBranch->branch_number != "SFI00001"
                    );
                }

                //head office - employee statutories (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->employee_sss),
                        'Employee SSS Contribution',
                        $eeSSSDebit,
                        $eeSSSVendor,
                        $eeSSSVendorName,
                        $eeSSSCredit,
                        $branchHOShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->employee_philhealth),
                        'Employee Philhealth Contribution',
                        $eePhilHealthDebit,
                        $eePhilHealthVendor,
                        $eePhilHealthVendorName,
                        $eePhilHealthCredit,
                        $branchHOShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->employee_pagibig),
                        'Employee Pag-Ibig Contribution',
                        $eePagibigDebit,
                        $eePagibigVendor,
                        $eePagibigVendorName,
                        $eePagibigCredit,
                        $branchHOShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
                }

                //head office - deduction (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    if ($payrollCash->is_maternity) {
                        continue;
                    }

                    $cashEmployeeDeductionGroups = $this->buildCashDeductionGroupsForPayroll($payrollCash);
                    if (!empty($cashEmployeeDeductionGroups)) {
                        [$sourceVendorNo, $sourceVendorName] = $this->resolveCashSourceVendor(
                            $payrollCash,
                            $cashPayrollVendorNo,
                            $cashPayrollVendorName
                        );
                        $entries = array_merge(
                            $entries,
                            $this->buildAmortizationEntries(
                                $cashEmployeeDeductionGroups,
                                $branchShortName . '|' . $start_date . '-' . $end_date,
                                $branchHOId,
                                $branchHOName,
                                $branchHONumber,
                                $sourceVendorNo,
                                $sourceVendorName,
                                $temporaryAccountA,
                                $temporaryAccountB
                            )
                        );
                    }
                }

                //head office - with_holding_tax (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    if ($payrollCash->is_maternity) {
                        continue;
                    }

                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->withholding_tax),
                        'Withholding Tax',
                        $withHoldingTaxDebit,
                        $withHoldingTaxVendor,
                        $withHoldingTaxVendorName,
                        $withHoldingTaxCredit,
                        $branchShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
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
                            'ven_no'              => $payrollEmployeeCashVendorNo,
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
                            'ven_no'              => $payrollEmployeeCashVendorNo,
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
                            'ven_no'              => $cashPayrollVendorNo,
                            'ven_name'            => $cashPayrollVendorName,
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
                            'ven_no'              => $cashPayrollVendorNo,
                            'ven_name'            => $cashPayrollVendorName,
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

                //branch - payroll payable (bank)
                if ($payrollEmployeeBankSums['netpay'] > 0) {
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                            'amount'              => $payrollEmployeeBankSums['netpay'],
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
                if (!empty($bankDeductionGroups)) {
                    $entries = array_merge(
                        $entries,
                        $this->buildAmortizationEntries(
                            $bankDeductionGroups,
                            $branchShortName . '|' . $start_date . '-' . $end_date,
                            $branchHOId,
                            $branchHOName,
                            $branchHONumber,
                            $vendorHONo,
                            $vendorHOName,
                            $temporaryAccountA,
                            $temporaryAccountB
                        )
                    );
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
                if ($bankAdditionalAndIncentiveSums > 0) {
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

                //branch - payroll payable (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    if ($payrollCash->is_maternity) {
                        continue;
                    }

                    $this->appendCashPayrollPayableEntries(
                        $entries,
                        $payrollCash,
                        $branchShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $start_date . '-' . $end_date,
                        $branchId,
                        $branchName,
                        $branchNumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $vendorHONo,
                        $vendorHOName,
                        $payrollBranch,
                        $isPayrollEmployeeCashOnHold ? 1 : 0
                    );
                }

                //branch - employee statutories (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->employee_sss),
                        'Employee SSS Contribution',
                        $eeSSSDebit,
                        $eeSSSVendor,
                        $eeSSSVendorName,
                        $eeSSSCredit,
                        $branchShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->employee_philhealth),
                        'Employee Philhealth Contribution',
                        $eePhilHealthDebit,
                        $eePhilHealthVendor,
                        $eePhilHealthVendorName,
                        $eePhilHealthCredit,
                        $branchShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->employee_pagibig),
                        'Employee Pag-Ibig Contribution',
                        $eePagibigDebit,
                        $eePagibigVendor,
                        $eePagibigVendorName,
                        $eePagibigCredit,
                        $branchShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
                }

                //branch - deduction (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    if ($payrollCash->is_maternity) {
                        continue;
                    }

                    $cashEmployeeDeductionGroups = $this->buildCashDeductionGroupsForPayroll($payrollCash);
                    if (!empty($cashEmployeeDeductionGroups)) {
                        [$sourceVendorNo, $sourceVendorName] = $this->resolveCashSourceVendor(
                            $payrollCash,
                            $cashPayrollVendorNo,
                            $cashPayrollVendorName
                        );
                        $entries = array_merge(
                            $entries,
                            $this->buildAmortizationEntries(
                                $cashEmployeeDeductionGroups,
                                $branchShortName . '|' . $start_date . '-' . $end_date,
                                $branchHOId,
                                $branchHOName,
                                $branchHONumber,
                                $sourceVendorNo,
                                $sourceVendorName,
                                $temporaryAccountA,
                                $temporaryAccountB
                            )
                        );
                    }
                }

                //branch - with_holding_tax (cash)
                foreach ($payrollEmployeeCashQuery as $payrollCash) {
                    if ($payrollCash->is_maternity) {
                        continue;
                    }

                    $this->appendCashContributionEntries(
                        $entries,
                        $payrollCash,
                        $this->parseMoneyValue($payrollCash->withholding_tax),
                        'Withholding Tax',
                        $withHoldingTaxDebit,
                        $withHoldingTaxVendor,
                        $withHoldingTaxVendorName,
                        $withHoldingTaxCredit,
                        $branchShortName . '|' . $start_date . '-' . $end_date,
                        $branchHOId,
                        $branchHOName,
                        $branchHONumber,
                        $cashPayrollVendorNo,
                        $cashPayrollVendorName,
                        $temporaryAccountA,
                        $temporaryAccountB
                    );
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
                            'ven_no'              => $payrollEmployeeCashVendorNo,
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
                            'ven_no'              => $payrollEmployeeCashVendorNo,
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
                            'ven_no'              => $cashPayrollVendorNo,
                            'ven_name'            => $cashPayrollVendorName,
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
                            'ven_no'              => $cashPayrollVendorNo,
                            'ven_name'            => $cashPayrollVendorName,
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

                    if ($this->apsysAvailable) {
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
                }

                if ($this->apsysAvailable) {
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
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                $hasError = true;
                Log::error('Failed to create entry', [
                    'entry' => $entry,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($hasError) {
            $this->notifyJobFailed();
        } else {
            $this->notifyJobFinished();
        }
    }
}
