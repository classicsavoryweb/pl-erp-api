<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ReportsBDOCashCardHistoryExport implements FromCollection, WithHeadings, WithStyles
{
    protected Collection $bdoCashCard;

    public function __construct(Collection $bdoCashCard)
    {
        $this->bdoCashCard = $bdoCashCard;
    }

    public function collection()
    {
        $grouped = $this->bdoCashCard->groupBy('employee_number');

        return $grouped->map(function ($employeeCards, $employeeNumber) {

            $allBankDetails = $employeeCards->flatMap(function ($card) {
                return $card->bank_details;
                
            })->sortByDesc(['created_at', 'id'])->values();

            $latestCard = $allBankDetails->first();
            $oldCards = $allBankDetails->slice(1);

            $row = [
                $employeeCards->first()->employee_number ?? '',
                $employeeCards->first()->full_name ?? '',
                $latestCard->bank_number ?? '',
                $latestCard->remarks ?? '',
            ];

            foreach ($oldCards as $oldCard) {
                $row[] = $oldCard->bank_number ?? '';
                $row[] = $oldCard->remarks ?? '';
            }

            return collect($row);
        })->values();
    }

    public function headings(): array
    {
        $headings = [
            'Employee Number',
            'Employee Name',
            'Latest Cash Card No.',
            'Latest Remarks',
        ];

        $maxOldCards = $this->bdoCashCard
        ->groupBy('employee_number')
        ->map(fn($cards) => $cards->flatMap(fn($card) => $card->bank_details)->count() - 1)
        ->max();

        for ($i = 1; $i <= $maxOldCards; $i++) {
            $headings[] = "Old Cash Card No. $i";
            $headings[] = "Old Remarks $i";
        }

        return $headings;
    }

    private function getExcelColumnLetter($colIndex) {
        $colName = '';
        while ($colIndex >= 0) {
            $colName = chr($colIndex % 26 + 65) . $colName;
            $colIndex = floor($colIndex / 26) - 1;
        }
        return $colName;
    }

    public function styles(Worksheet $sheet)
    {
        $headings = $this->headings();

        $maxColIndex = count($headings) - 1;
        $lastColumnLetter = $this->getExcelColumnLetter($maxColIndex);

        $headerRow    = 1;
        $dataStartRow = $headerRow + 1;

        $columns = [];
        for ($i = 0; $i <= $maxColIndex; $i++) {
            $columns[] = $this->getExcelColumnLetter($i);
        }

        $maxLengths = array_fill_keys($columns, 0);

        $rows = $this->bdoCashCard->groupBy('employee_number');

        $rowNumber = $dataStartRow;

        foreach ($rows as $employeeNumber => $employeeCards) {
            $allBankDetails = $employeeCards->flatMap(fn($card) => $card->bank_details)
            ->sortByDesc(['created_at', 'id'])->values();

            $latest = $allBankDetails->first();

            $data = [
                $employeeCards->first()->employee_number ?? '',
                $employeeCards->first()->full_name ?? '',
                $latest->bank_number ?? '',
                $latest->remarks ?? '',
            ];

            foreach ($allBankDetails->slice(1) as $oldCard) {
                $data[] = $oldCard->bank_number ?? '';
                $data[] = $oldCard->remarks ?? '';
            }

            foreach ($columns as $colIndex => $col) {
                $value = $data[$colIndex] ?? '';
                
                $headerCell = $sheet->getCell($col . $headerRow);
                $headerLength = strlen((string) $headerCell->getValue());
                $maxContentLength = max($maxLengths[$col], $headerLength);
                $maxLengths[$col] = max($maxLengths[$col], strlen((string) $value));

                $sheet->setCellValueExplicit($col . $rowNumber, (string)$value, DataType::TYPE_STRING);
            }

            $rowNumber++;
        }

        $lastRow = $rowNumber - 1;

        $sheet->getStyle('A' . $headerRow . ':' . $lastColumnLetter . $lastRow)->applyFromArray([
            'font' => [
                'name' => 'Arial',
                'size' => 11,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ]
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A' . $headerRow . ':' . $lastColumnLetter . $headerRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'D3D3D3'],
            ],
        ]);

        foreach ($columns as $col) {
            $sheet->getColumnDimension($col)
                ->setWidth($maxLengths[$col] + 2)
                ->setAutoSize(true);
        }
    }
}
