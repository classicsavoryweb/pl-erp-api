<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class ReportsPagibigLoansExport implements FromCollection, WithHeadings, WithColumnFormatting, WithStyles
{
    public function columnFormats(): array
    {
        return [
            'B' => '# ?/?',
        ];
    }

    protected Collection $payrollEmployee;
    protected int $withDash;

    public function __construct(Collection $payrollEmployee, int $withDash = 0)
    {
        $this->payrollEmployee = $payrollEmployee;
        $this->withDash = $withDash;
    }

    public function collection()
    {
        return $this->payrollEmployee->map(function ($unique_payrollEmployee) {
            $pagibigId = $unique_payrollEmployee->employee->pagibig;

            if ($this->withDash == 0) {
                $pagibigId = (string) str_replace('-', '', $pagibigId);
            }

            return [
                $unique_payrollEmployee->employee->employee_number,
                $pagibigId,
                $unique_payrollEmployee->employee->last_name,
                $unique_payrollEmployee->employee->first_name,
                $unique_payrollEmployee->employee->middle_name,
                $this->formatNumber($unique_payrollEmployee->amount),
                $unique_payrollEmployee->employee->birthday,
                $unique_payrollEmployee->pay_date
            ];
        });
    }

    private function formatNumber($value)
    {
        return $value == 0 ? '' : number_format($value, 2, '.', '');
    }

    public function headings(): array
    {
        $headings = [
            'Employee Number',
            'Pagibig ID',
            'Last Name',
            'First Name',
            'Middle Name',
            'Amount Paid',
            'Birthday',
            'Pay Date'
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
        $dataCount     = $this->payrollEmployee->count();
        $lastRow       = $dataStartRow + max($dataCount - 1, 0);

        $sheet->getStyle('A' . $headerRow . ':' . $lastColumnLetter . $lastRow)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // $sheet->freezePane('B1');

        $columns = [];
        for ($i = 0; $i <= $maxColIndex; $i++) {
            $columns[] = $this->getExcelColumnLetter($i);
        }

        $maxLengths = array_fill_keys($columns, 0);
        $rows = $this->payrollEmployee;

        foreach ($rows as $index => $unique_payrollEmployee) {

            $pagibigId = $unique_payrollEmployee->employee->pagibig;

            if ($this->withDash == 0) {
                $pagibigId = (string) str_replace('-', '', $pagibigId);
            }

            $rowNumber = $dataStartRow + $index;
            $data = [
                $index + 1,
                $unique_payrollEmployee->employee->employee_number,
                $pagibigId,
                $unique_payrollEmployee->employee->last_name,
                $unique_payrollEmployee->employee->first_name,
                $unique_payrollEmployee->employee->middle_name,
                $this->formatNumber($unique_payrollEmployee->amount),
                $unique_payrollEmployee->employee->birthday,
                $unique_payrollEmployee->pay_date
            ];

            foreach ($columns as $colIndex => $col) {
                if (isset($data[$colIndex])) {
                    $headerCell = $sheet->getCell($col . $headerRow);
                    $headerLength = strlen((string) $headerCell->getValue());
                    $maxContentLength = max($maxLengths[$col], $headerLength);

                    $cellValue = (string) $data[$colIndex];
                    $maxLengths[$col] = max($maxLengths[$col], strlen($cellValue));

                    $sheet->getColumnDimension($col)->setWidth($maxContentLength + 2)->setAutoSize(true);
                }
            }

            $headerRange = 'A' . $headerRow . ':' . $lastColumnLetter . $headerRow;
            $sheet->getStyle($headerRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ]
                ],
                'font' => ['bold' => true],
            ]);

            $sheet->getStyle('A' . $rowNumber . ':' . $lastColumnLetter . $rowNumber)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ]
                ]
            ]);
        }
    }
}
