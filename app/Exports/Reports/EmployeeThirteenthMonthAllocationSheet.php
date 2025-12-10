<?php

namespace App\Exports\Reports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeThirteenthMonthAllocationSheet implements FromCollection, WithHeadings, WithStyles
{
    protected Collection $employeesMonthlyAllocationData;

    public function __construct(Collection $employeesMonthlyAllocationData)
    {
        $this->employeesMonthlyAllocationData = $employeesMonthlyAllocationData;
    }

    public function collection()
    {
        $dataStartRow = 3;

        $employeeRows = $this->employeesMonthlyAllocationData->map(function ($employeeSummary) {
            $employee = Employee::find($employeeSummary->employee_id);

            return [
                $employee->employee_number,
                $employee->full_name,
                $employeeSummary->branch_short_name,
                $employeeSummary->basic_pay_1st_cutoff,
                $employeeSummary->basic_pay_2nd_cutoff,
                $employeeSummary->total,
                $employeeSummary->net_claim_13th_month,
                $employeeSummary->net_claim_pay_leave,
                $employeeSummary->net_claim_additional,
            ];
        });

        $firstDataRow = $dataStartRow;
        $lastDataRow  = $dataStartRow + $employeeRows->count() - 1;

        $employeeRows->push([
            '',
            'TOTAL',
            '',
            "=SUM(D{$firstDataRow}:D{$lastDataRow})",
            "=SUM(E{$firstDataRow}:E{$lastDataRow})",
            "=SUM(F{$firstDataRow}:F{$lastDataRow})",
            "=SUM(G{$firstDataRow}:G{$lastDataRow})",
            "=SUM(H{$firstDataRow}:H{$lastDataRow})",
            "=SUM(I{$firstDataRow}:I{$lastDataRow})",
        ]);

        return $employeeRows;
    }

    public function headings(): array
    {
        $currentYear = $this->employeesMonthlyAllocationData->isNotEmpty() ? $this->employeesMonthlyAllocationData->first()->year : now()->year;
        $dataStartRow = 3;
        $count        = $this->employeesMonthlyAllocationData->count();
        $lastDataRow  = $dataStartRow + $count - 1;

        return [
            [
                "Monthly Allocation {$currentYear}",
                "",
                "",
                "=SUM(D{$dataStartRow}:D{$lastDataRow})",
                "=SUM(E{$dataStartRow}:E{$lastDataRow})",
                "=SUM(F{$dataStartRow}:F{$lastDataRow})",
                "=SUM(G{$dataStartRow}:G{$lastDataRow})",
                "=SUM(H{$dataStartRow}:H{$lastDataRow})",
                "=SUM(I{$dataStartRow}:I{$lastDataRow})",
            ],

            [
                'Employee Number',
                'Employee Name',
                'Branch',
                'Basic Pay (1st Cutoff)',
                'Basic Pay (2nd Cutoff)',
                'Total',
                'Net Claim (13th Month)',
                'Net Claim (Payleave)',
                'Net Claim (Additional 3.5%)',
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle("A1:I2")->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Aptos Narrow',
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'D3D3D3'],
            ],
        ]);

        $sheet->freezePane('A3');

        $dataStartRow = 3;
        $collection   = $this->collection();
        $lastRow = $sheet->getHighestRow();

        foreach (range('D', 'I') as $col) {
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        $sheet->getStyle("A1:I{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ]);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
