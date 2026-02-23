<?php

namespace App\Exports\Compensation;

use App\Models\Accounting\Vendor;
use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EntryExport implements FromCollection, WithHeadings, WithStyles
{
    protected Collection $entry;
    protected array $vendorNames = [];
    protected array $accountNames = [];

    public function __construct(Collection $entry)
    {
        $this->entry = $entry;
        $this->primeApsysLookups();
    }

    private function primeApsysLookups(): void
    {
        try {
            DB::connection('apsys')->getPdo();
        } catch (\Throwable $e) {
            Log::warning('APSYS connection unavailable; exporting entries without APSYS.', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $vendorNos = $this->entry->pluck('vendor_no')->filter()->unique()->values();
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

            $header = $rows->first();
            $debit  = $rows->where('balance_type', 'Debit')->first();
            $credit = $rows->where('balance_type', 'Credit')->first();

            $vendorName = $this->vendorNames[$header->vendor_no] ?? '';

            $branchName = $header->branch?->name ?? '';

            $exportRows->push([
                $entryId,
                $vendorName,
                $header->pay_date,
                $branchName,
                $header->document_no,
                number_format($header->amount, 2),
                "Single",
                $header->status
            ]);

            if ($debit) {
                $acctName = $this->accountNames[$debit->account_no] ?? '';

                $exportRows->push([
                    "",
                    $debit->account_no,
                    $acctName,
                    "",
                    number_format($debit->amount, 2),
                    0,
                    "",
                    ""
                ]);
            }

            if ($credit) {
                $acctName = $this->accountNames[$credit->account_no] ?? '';

                $exportRows->push([
                    "",
                    $credit->account_no,
                    $acctName,
                    "",
                    0,
                    number_format($credit->amount, 2),
                    "",
                    ""
                ]);
            }
        }

        return $exportRows;
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
}
