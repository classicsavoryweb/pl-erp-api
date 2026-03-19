<?php

namespace App\Exports\Compensation;

use App\Models\Employee;
use App\Models\Accounting\Vendor;
use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EntryExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting
{
    protected Collection $entry;
    protected array $vendorNames = [];
    protected array $accountNames = [];
    protected array $employeeNames = [];
    private const FALLBACK_VENDOR_NAMES = [
        'HO' => 'HO',
        'GOV0000000001' => 'Home Development Mutual Fund',
        'GOV0000000002' => 'Social Security System',
        'GOV0000000003' => 'Home Development Mutual Fund',
        'GOV0000000004' => 'Philippine Health Insurance Corporation',
        'GOV0000000005' => 'Social Security System',
        'INC0000000003' => 'CBS Handy Salary Loan',
        'INC0000000004' => 'Tony Ting Food Concepts Inc.',
        'INB0000000008' => 'HO',
        'PRO0000000005' => 'Chua, Chua & Company',
    ];
    private const FALLBACK_ACCOUNT_NAMES = [
        '3000001' => 'CASH ADVANCES - PENALTIES, SHORT, BREAKAGES',
        '3000003' => 'HDMF LOAN PAYABLE',
        '3000004' => 'PAG-IBIG PAYABLE',
        '3000005' => 'PHIC PAYABLE',
        '3000006' => 'SSS LOAN PAYABLE',
        '3000007' => 'SSS PAYABLE',
        '3000008' => 'SSS, PHIC, PAG-IBIG CONTRIBUTION',
        '4000000' => 'Accounts Payable',
        '4500009' => 'Advances - HO',
        '4500012' => 'Advances - MAT',
        '4600001' => 'BANK SALARY LOAN PAYABLE',
        '4600009' => 'WITHHOLDING TAX PAYABLE',
        '7000011' => 'SALARIES & WAGES - STORE',
        '7000020' => 'COMMISSION & INCENTIVE - STORE',
    ];
    private const TEMP_ACCOUNT_A_NO = '4600003';
    private const TEMP_ACCOUNT_B_NO = '4600004';
    private const TEMP_ACCOUNT_A_NAME = 'Temporary Account A';
    private const TEMP_ACCOUNT_B_NAME = 'Temporary Account B';

    public function __construct(Collection $entry)
    {
        $this->entry = $entry;
        $this->primeApsysLookups();
    }

    private function primeApsysLookups(): void
    {
        $vendorNos = $this->entry->pluck('vendor_no')->filter()->unique()->values();
        if ($vendorNos->isNotEmpty()) {
            $this->employeeNames = Employee::query()
                ->whereIn('employee_number', $vendorNos)
                ->get(['employee_number', 'first_name', 'middle_name', 'last_name'])
                ->mapWithKeys(function (Employee $employee) {
                    return [$employee->employee_number => $employee->full_name];
                })
                ->all();
        }

        try {
            DB::connection('apsys')->getPdo();
        } catch (\Throwable $e) {
            Log::warning('APSYS connection unavailable; exporting entries without APSYS.', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if ($vendorNos->isNotEmpty()) {
            $this->vendorNames = Vendor::whereIn('ven_no', $vendorNos)
                ->pluck('ven_name', 'ven_no')
                ->all();
        }

        $accountNos = $this->entry->pluck('account_no')->filter()->unique()->values();
        if ($accountNos->isNotEmpty()) {
            $this->accountNames = Account::whereIn('acc_no', $accountNos)
                ->pluck('acc_name', 'acc_no')
                ->all();
        }
    }

    public function collection()
    {
        $grouped = $this->entry->groupBy('entry_id');
        $exportRows = collect();

        foreach ($grouped as $entryId => $rows) {

            $header = $rows->first(fn ($row) => !empty($row->vendor_no)) ?? $rows->first();
            $debit  = $rows->where('balance_type', 'Debit')->first();
            $credit = $rows->where('balance_type', 'Credit')->first();

            $vendorName = $this->resolveVendorName($header->vendor_no ?? null);

            $branchName = $header->branch?->name ?? '';

            $exportRows->push([
                $entryId,
                $vendorName,
                $header->pay_date,
                $branchName,
                $header->document_no,
                (float) $header->amount,
                "Single",
                $header->status
            ]);

            if ($debit) {
                [$accountNo, $acctName] = $this->resolveAccountDisplay($debit, $header);

                $exportRows->push([
                    "",
                    $accountNo,
                    $acctName,
                    "",
                    (float) $debit->amount,
                    0.0,
                    "",
                    ""
                ]);
            }

            if ($credit) {
                [$accountNo, $acctName] = $this->resolveAccountDisplay($credit, $header);

                $exportRows->push([
                    "",
                    $accountNo,
                    $acctName,
                    "",
                    0.0,
                    (float) $credit->amount,
                    "",
                    ""
                ]);
            }
        }

        return $exportRows;
    }

    private function resolveVendorName(?string $vendorNo): string
    {
        if (!$vendorNo) {
            return 'HO';
        }

        return $this->vendorNames[$vendorNo]
            ?? $this->employeeNames[$vendorNo]
            ?? self::FALLBACK_VENDOR_NAMES[$vendorNo]
            ?? $vendorNo;
    }

    private function resolveAccountDisplay(object $row, object $header): array
    {
        $accountNo = $row->account_no ?? '';
        $accountName = $this->resolveAccountName($row->account_no ?? null, $row->description ?? null);

        if ($accountNo) {
            return [$accountNo, $accountName];
        }

        if (($row->balance_type ?? null) === 'Credit') {
            return [self::TEMP_ACCOUNT_B_NO, self::TEMP_ACCOUNT_B_NAME];
        }

        if (($row->balance_type ?? null) === 'Debit') {
            return [self::TEMP_ACCOUNT_A_NO, self::TEMP_ACCOUNT_A_NAME];
        }

        return ['', $accountName];
    }

    private function resolveAccountName(?string $accountNo, ?string $fallbackDescription): string
    {
        if ($accountNo && isset($this->accountNames[$accountNo])) {
            return $this->accountNames[$accountNo];
        }

        if ($accountNo && isset(self::FALLBACK_ACCOUNT_NAMES[$accountNo])) {
            return self::FALLBACK_ACCOUNT_NAMES[$accountNo];
        }

        if ($accountNo === self::TEMP_ACCOUNT_A_NO) {
            return self::TEMP_ACCOUNT_A_NAME;
        }

        if ($accountNo === self::TEMP_ACCOUNT_B_NO) {
            return self::TEMP_ACCOUNT_B_NAME;
        }

        if (!empty($fallbackDescription)) {
            return $fallbackDescription;
        }

        return '';
    }

    public function headings(): array
    {
        return [
            'TxnNO',
            'VendName',
            'TxnDate',
            'BranchNm',
            'DocNo',
            'TxnAmt',
            'EntryType',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Aptos Narrow',
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'D3D3D3'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ]);

        $blue = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'b4e9f9']
            ]
        ];

        $borderBox = [
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '616768']
                ]
            ]
        ];

        $row = 2;
        $grouped = $this->entry->groupBy('entry_id');

        foreach ($grouped as $entryId => $rows) {

            $startRow = $row; 

            $hasDebit  = $rows->where('balance_type', 'Debit')->count() > 0;
            $hasCredit = $rows->where('balance_type', 'Credit')->count() > 0;

            $row++;

            if ($hasDebit) {
                $sheet->getStyle("B{$row}")->applyFromArray($blue);
                $sheet->getStyle("C{$row}")->applyFromArray($blue);
                $sheet->getStyle("D{$row}")->applyFromArray($blue);
                $sheet->getStyle("E{$row}")->applyFromArray($blue);
                $sheet->getStyle("F{$row}")->applyFromArray($blue);
                $row++;
            }

            if ($hasCredit) {
                $sheet->getStyle("B{$row}")->applyFromArray($blue);
                $sheet->getStyle("C{$row}")->applyFromArray($blue);
                $sheet->getStyle("D{$row}")->applyFromArray($blue);
                $sheet->getStyle("E{$row}")->applyFromArray($blue);
                $sheet->getStyle("F{$row}")->applyFromArray($blue);
                $row++;
            }

            $endRow = $row - 1;

            $sheet->getStyle("A{$startRow}:H{$endRow}")
                ->applyFromArray($borderBox);
        }

        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }

    public function columnFormats(): array
    {
        $accountingFormat = '_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)';

        return [
            'E' => $accountingFormat,
            'F' => $accountingFormat,
        ];
    }
}
