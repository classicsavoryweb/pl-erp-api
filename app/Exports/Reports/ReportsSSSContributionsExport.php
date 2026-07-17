<?php

namespace App\Exports\Reports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class ReportsSSSContributionsExport implements FromCollection, WithHeadings, WithColumnFormatting, WithStyles
{
    public function columnFormats(): array
    {
        return [
            'C' => '# ?/?',
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
            $employee_sss_first_cutoff = 0;
            $employee_sss_second_cutoff = 0;
            $employer_sss_first_cutoff = 0;
            $employer_sss_second_cutoff = 0;

            $unique_payrollEmployee = $employeePayrolls->first();
            $monthlySalary = 0;

            foreach ($employeePayrolls as $payroll) {
                $start_date_day = Carbon::parse($payroll->start_date)->day;

                if ($start_date_day == 26) {
                    $employee_sss_first_cutoff = $payroll->employee_sss;
                    $employer_sss_first_cutoff = $payroll->employer_sss;
                } elseif ($start_date_day == 11) {
                    $employee_sss_second_cutoff = $payroll->employee_sss;
                    $employer_sss_second_cutoff = $payroll->employer_sss;
                }

                $monthlySalary += $payroll->gross_pay;
            }

            $sssId = $unique_payrollEmployee->employee->sss;

            $middleInitial = strtoupper(substr($unique_payrollEmployee->employee->middle_name, 0, 1));

            if ($this->withDash == 0) {
                $sssId = (string) str_replace('-', '', $sssId);
            }

            return [
                '03-92068417',
                $unique_payrollEmployee->employee->current_branch->short_name,
                $sssId,
                $unique_payrollEmployee->employee->employee_number,
                $unique_payrollEmployee->employee->last_name,
                $unique_payrollEmployee->employee->first_name,
                $middleInitial,
                $this->formatNumber($monthlySalary),
                $unique_payrollEmployee->employee->employee_status->description,
                $unique_payrollEmployee->employee->date_hired,
                $unique_payrollEmployee->employee->current_position->description,
                $this->formatNumber($employee_sss_first_cutoff),
                $this->formatNumber($employee_sss_second_cutoff),
                $this->formatNumber($employer_sss_first_cutoff),
                $this->formatNumber($employer_sss_second_cutoff),
                $this->formatNumber($employee_sss_first_cutoff + $employee_sss_second_cutoff),
                $this->formatNumber($employer_sss_first_cutoff + $employer_sss_second_cutoff),
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
            'Company SSS ID',
            'Branch Code',
            'Employee SSS ID',
            'Employee Number',
            'Last Name',
            'First Name',
            'Middle Initial',
            'Monthly Salary',
            'Remarks/Status',
            'Date Hired',
            'Position',
            '1st Cutoff (EE Share)',
            '2nd Cutoff (EE Share)',
            '1st Cutoff (ER Share)',
            '2nd Cutoff (ER Share)',
            'Total (EE Share)',
            'Total (ER Share)',
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
