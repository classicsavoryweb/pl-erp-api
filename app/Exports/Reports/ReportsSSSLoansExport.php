<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class ReportsSSSLoansExport implements FromCollection, WithHeadings, WithColumnFormatting, WithStyles
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
        return $this->payrollEmployee->groupBy("employee_id")->map(function ($sssDeductions) {

            $unique_payrollEmployee = $sssDeductions->first();
            $sssId = $unique_payrollEmployee->employee->sss;
            $amount = 0;

            if ($this->withDash == 0) {
                $sssId = (string) str_replace('-', '', $sssId);
            }

            foreach ($sssDeductions as $deductions) {
                $amount += $deductions->amount;
            }

            return [
                $unique_payrollEmployee->employee->employee_number,
                $sssId,
                $unique_payrollEmployee->employee->last_name,
                $unique_payrollEmployee->employee->first_name,
                $unique_payrollEmployee->employee->middle_name,
                $this->formatNumber($amount),
                $unique_payrollEmployee->employee->birthday
            ];
        });
    }

    private function formatNumber($value)
    {
        return $value == 0 ? '' : number_format($value, 2, '.', ',');
    }

    public function headings(): array
    {
        $headings = [
            'Employee Number',
            'SSS ID',
            'Last Name',
            'First Name',
            'Middle Name',
            'Amount Paid',
            'Birthday'
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
        $rows = $this->payrollEmployee->groupBy('employee_id');

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

        foreach ($rows as $index => $employeePayrolls) {

            $unique_payrollEmployee = $employeePayrolls->first();

            $sssId = $unique_payrollEmployee->employee->sss;

            if ($this->withDash == 0) {
                $sssId = (string) str_replace('-', '', $sssId);
            }

            $data = [
                $index + 1,
                $unique_payrollEmployee->employee->employee_number,
                $sssId,
                $unique_payrollEmployee->employee->last_name,
                $unique_payrollEmployee->employee->first_name,
                $unique_payrollEmployee->employee->middle_name,
                $this->formatNumber($unique_payrollEmployee->amount),
                $unique_payrollEmployee->employee->birthday
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
        }
    }
}
