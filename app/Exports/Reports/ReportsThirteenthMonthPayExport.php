<?php

namespace App\Exports\Reports;

use App\Models\Employee;
use App\Models\PayrollList;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportsThirteenthMonthPayExport implements FromCollection, WithHeadings, WithStyles
{
    protected Collection $thirteenthMonthsData;

    public function __construct(Collection $thirteenthMonthsData)
    {
        $this->thirteenthMonthsData = $thirteenthMonthsData;
    }

    public function collection()
    {
        return $this->thirteenthMonthsData->map(function ($thirteenthMonth) {
            $employee = Employee::find($thirteenthMonth->employee_id);
            $payrollListEmployee = PayrollList::where('employee_id', $employee->id)->first();

            $bankType = null;

            if($payrollListEmployee->is_bank === 1) {
                $bankType = 'Bank';
            } else {
                $bankType = 'Cash';
            }

            return [
                $employee->employee_number,
                $employee->full_name,
                $employee->current_branch->name,
                $employee->current_position->description,
                $bankType,

                $thirteenthMonth->december_2nd_cutoff,
                $thirteenthMonth->january_1st_cutoff,
                $thirteenthMonth->january_2nd_cutoff,
                $thirteenthMonth->february_1st_cutoff,
                $thirteenthMonth->february_2nd_cutoff,
                $thirteenthMonth->march_1st_cutoff,
                $thirteenthMonth->march_2nd_cutoff,
                $thirteenthMonth->april_1st_cutoff,
                $thirteenthMonth->april_2nd_cutoff,
                $thirteenthMonth->may_1st_cutoff,
                $thirteenthMonth->may_2nd_cutoff,
                $thirteenthMonth->june_1st_cutoff,
                $thirteenthMonth->june_2nd_cutoff,
                $thirteenthMonth->july_1st_cutoff,
                $thirteenthMonth->july_2nd_cutoff,
                $thirteenthMonth->august_1st_cutoff,
                $thirteenthMonth->august_2nd_cutoff,
                $thirteenthMonth->september_1st_cutoff,
                $thirteenthMonth->september_2nd_cutoff,
                $thirteenthMonth->october_1st_cutoff,
                $thirteenthMonth->october_2nd_cutoff,
                $thirteenthMonth->november_1st_cutoff,
                $thirteenthMonth->november_2nd_cutoff,
                $thirteenthMonth->december_1st_cutoff,

                $thirteenthMonth->total,
                $thirteenthMonth->net_claim,
            ];
        });
    }

    public function headings(): array
    {
        $firstYear = $this->thirteenthMonthsData->first()->year - 1;
        $currentYear = $this->thirteenthMonthsData->first()->year;

        return [
            array_merge(
                ['13th Month Pay', '', '', '', ''], 
                [$firstYear, $currentYear], 
                array_fill(0, 24, '') 
            ),

            [
                'Employee Number',
                'Name',
                'Branch',
                'Position',
                'Bank Type',
                'December',
                'January', 'January',
                'February', 'February',
                'March', 'March',
                'April', 'April',
                'May', 'May',
                'June', 'June',
                'July', 'July',
                'August', 'August',
                'September', 'September',
                'October', 'October',
                'November', 'November',
                'December',
                'Total',
                'Net Claim'
            ],

            [
                'Employee Number',
                'Name',
                'Branch',
                'Position',
                'Bank Type',
                '12/11 to 25',
                '12/26 to 10', '1/11 to 25',
                '1/26 to 10', '2/11 to 25',
                '2/26 to 10', '3/11 to 25',
                '3/26 to 10', '4/11 to 25',
                '4/26 to 10', '5/11 to 25',
                '5/26 to 10', '6/11 to 25',
                '6/26 to 10', '7/11 to 25',
                '7/26 to 10', '8/11 to 25',
                '8/26 to 10', '9/11 to 25',
                '9/26 to 10', '10/11 to 25',
                '10/26 to 10', '11/11 to 25',
                '11/26 to 10',
                'Total',
                'Net Claim'
            ]
        ];
    }

    private function getExcelColumnLetter($colIndex)
    {
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
        $numColumns = count($headings[1]);
        $lastColumnLetter = $this->getExcelColumnLetter($numColumns - 1);

        $sheet->mergeCells("A1:B1"); 
        $sheet->mergeCells("C1:E1"); 
        $sheet->mergeCells("F1:F1"); 
        $sheet->mergeCells("G1:{$lastColumnLetter}1"); 

        $sheet->mergeCells("A2:A3");
        $sheet->mergeCells("B2:B3");
        $sheet->mergeCells("C2:C3");
        $sheet->mergeCells("D2:D3");
        $sheet->mergeCells("E2:E3");

        $secondLastColLetter = $this->getExcelColumnLetter($numColumns - 2);
        $sheet->mergeCells("{$secondLastColLetter}2:{$secondLastColLetter}3");
        $sheet->mergeCells("{$lastColumnLetter}2:{$lastColumnLetter}3");

        $mergeMap = [
            [5, 5],   
            [6, 7],   
            [8, 9],   
            [10, 11],   
            [12, 13],  
            [14, 15], 
            [16, 17], 
            [18, 19], 
            [20, 21], 
            [22, 23], 
            [24, 25], 
            [26, 27], 
            [28, 28], 
        ];

        foreach ($mergeMap as $pair) {
            $start = $this->getExcelColumnLetter($pair[0]);
            $end   = $this->getExcelColumnLetter($pair[1]);
            $sheet->mergeCells("{$start}2:{$end}2");
        }

        $sheet->getStyle("A1:{$lastColumnLetter}3")->applyFromArray([
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

        $sheet->freezePane('C2');

        $dataStartRow = 4;
        $collection = $this->collection();
        $lastRow = $dataStartRow + $collection->count() - 1;

        $sheet->getStyle("A{$dataStartRow}:{$lastColumnLetter}{$lastRow}")
            ->applyFromArray([
                'font' => [
                    'name' => 'Aptos Narrow',
                    'size' => 11,
                ],
            ]);

        $numericColumnsStart = 4; 
        $numericColumnsEnd = 30;  
        for ($i = $numericColumnsStart; $i <= $numericColumnsEnd; $i++) {
            $colLetter = $this->getExcelColumnLetter($i);
            $sheet->getStyle("{$colLetter}{$dataStartRow}:{$colLetter}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        $sheet->getStyle("A1:{$lastColumnLetter}{$lastRow}")
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                    ]
                ]
            ]);

        for ($i = 0; $i < $numColumns; $i++) {
            $colLetter = $this->getExcelColumnLetter($i);

            $max = 0;
            for ($row = 1; $row <= $lastRow; $row++) {
                $val = (string)$sheet->getCell("{$colLetter}{$row}")->getValue();
                $max = max($max, strlen($val));
            }

            if ($colLetter === $secondLastColLetter) {
                $max += 5;
            }

            $sheet->getColumnDimension($colLetter)->setWidth($max + 2);
        }
    }
}
