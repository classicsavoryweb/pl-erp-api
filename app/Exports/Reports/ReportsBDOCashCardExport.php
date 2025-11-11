<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ReportsBDOCashCardExport implements FromCollection, WithHeadings, WithStyles
{

    protected Collection $bdoCashCard;

    public function __construct(Collection $bdoCashCard)
    {
        $this->bdoCashCard = $bdoCashCard;
    }

    public function collection()
    {
        return $this->bdoCashCard->map(function ($unique_bdoCashCard) {

            return [
                $unique_bdoCashCard->employee_number,
                $unique_bdoCashCard->full_name,
                $unique_bdoCashCard->latest_bank_details?->bank_number ?? '',
                $unique_bdoCashCard->status,
            ];
        });
    }

    public function headings(): array
    {
        $headings = [
            'Employee Number',
            'Employee Name',
            'Bank Number',
            'Status'
        ];

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

        $headerRow     = 1;
        $dataStartRow  = $headerRow + 1;

        $columns = [];
        for ($i = 0; $i <= $maxColIndex; $i++) {
            $columns[] = $this->getExcelColumnLetter($i);
        }

        $maxLengths = array_fill_keys($columns, 0);
        $rows = $this->bdoCashCard;

        $dataCount     = $rows->count();
        $lastRow       = $dataStartRow + max($dataCount - 1, 0);

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

        $headerRange = 'A' . $headerRow . ':' . $lastColumnLetter . $headerRow;
        $sheet->getStyle($headerRange)->applyFromArray([
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

        foreach ($rows as $index => $unique_bdoCashCard) {

            $data = [
                $index + 1,
                $unique_bdoCashCard->employee_number,
                $unique_bdoCashCard->full_name,
                $unique_bdoCashCard->latest_bank_details?->bank_number ?? '',
                $unique_bdoCashCard->status,
            ];

            $rowNumber = $dataStartRow + $index; 

            foreach ($columns as $colIndex => $col) {
                if (isset($data[$colIndex])) {
                    $headerCell = $sheet->getCell($col . $headerRow);
                    $headerLength = strlen((string) $headerCell->getValue());
                    $cellValue = (string) $data[$colIndex];
                    $maxContentLength = max($maxLengths[$col], $headerLength, strlen($cellValue));

                    $maxLengths[$col] = $maxContentLength;

                    $sheet->setCellValueExplicit($col . $rowNumber, $cellValue, DataType::TYPE_STRING);

                    $sheet->getColumnDimension($col)->setWidth($maxContentLength + 2)->setAutoSize(true);
                }
            }
        }
    }
}
