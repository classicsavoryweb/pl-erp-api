<?php

namespace App\Exports\Reports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class ReportsPagibigContributionsExport implements FromCollection, WithHeadings, WithColumnFormatting, WithStyles
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
        return $this->payrollEmployee->groupBy('employee_id')->map(function ($employeePayrolls) {
            $employee_pagibig_first_cutoff = 0;
            $employee_pagibig_second_cutoff = 0;
            $employer_pagibig_first_cutoff = 0;
            $employer_pagibig_second_cutoff = 0;

            $unique_payrollEmployee = $employeePayrolls->first();

            foreach ($employeePayrolls as $payroll) {
                $start_date_day = Carbon::parse($payroll->start_date)->day;

                if ($start_date_day == 26) {
                    $employee_pagibig_first_cutoff = $payroll->employee_pagibig;
                    $employer_pagibig_first_cutoff = $payroll->employer_pagibig;
                } elseif ($start_date_day == 11) {
                    $employee_pagibig_second_cutoff = $payroll->employee_pagibig;
                    $employer_pagibig_second_cutoff = $payroll->employer_pagibig;
                }
            }

            $pagibigId = $unique_payrollEmployee->employee->pagibig;
            $sssId = $unique_payrollEmployee->employee->sss;

            if ($this->withDash == 0) {
                $pagibigId = (string) str_replace('-', '', $pagibigId);
                $sssId = (string) str_replace('-', '', $sssId);
            }

            return [
                $unique_payrollEmployee->employee->employee_number,
                $pagibigId,
                $sssId,
                $unique_payrollEmployee->employee->last_name,
                $unique_payrollEmployee->employee->first_name,
                $unique_payrollEmployee->employee->middle_name,
                $this->formatNumber($employee_pagibig_first_cutoff + $employee_pagibig_second_cutoff),
                $this->formatNumber($employer_pagibig_first_cutoff + $employer_pagibig_second_cutoff),
                $unique_payrollEmployee->employee->birthday,
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
            'Pagibig ID',
            'SSS ID',
            'Last Name',
            'First Name',
            'Middle Name',
            'Total (EE Share)',
            'Total (ER Share)',
            'Birth Date'
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

        foreach ($columns as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
