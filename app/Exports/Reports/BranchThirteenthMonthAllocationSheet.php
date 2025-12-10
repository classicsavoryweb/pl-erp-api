<?php

namespace App\Exports\Reports;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BranchThirteenthMonthAllocationSheet implements FromCollection, WithHeadings, WithStyles
{
    protected Collection $branchesMonthlyAllocationData;

    public function __construct(Collection $branchesMonthlyAllocationData)
    {
        $this->branchesMonthlyAllocationData = $branchesMonthlyAllocationData;
    }

    public function collection()
    {
        $branchesRows = $this->branchesMonthlyAllocationData->map(function ($branchSummary) {
            $branch = Branch::where('id', $branchSummary->branch_id)->first();

            return [
                $branch->short_name,
                $branchSummary->branch_net_claim_13th_month,
                $branchSummary->branch_net_claim_pay_leave,
                $branchSummary->branch_net_claim_additional,
                $branchSummary->branch_total,
            ];
        });

        return $branchesRows;
    }

    public function headings(): array
    {
        return [
            [
                'SUMMARY',
                '', '', '', ''
            ],

            [
                'Branches',
                '13th Month',
                'Payleave',
                'Additional 3.5%',
                'Total'
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle("A1:E2")->applyFromArray([
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

        $dataStartRow = 3;
        $collection   = $this->collection();
        $lastRow      = $dataStartRow + $collection->count() - 1;

        foreach (range('B', 'E') as $col) {
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        $sheet->getStyle("A1:E{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ]);

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
