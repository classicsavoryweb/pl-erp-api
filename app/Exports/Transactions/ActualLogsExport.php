<?php

namespace App\Exports\Transactions;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class ActualLogsExport implements FromCollection, WithHeadings, WithStyles, WithCustomStartCell
{
    protected Collection $actual_logs;

    public function __construct(Collection $actual_logs)
    {
        $this->actual_logs = $actual_logs;
    }

    public function collection()
    {
        return $this->actual_logs->map(function ($actual) {
            $punchTimesCollection = $actual->punches->pluck('punch_time');
            $punchCount = $punchTimesCollection->count();

            $punchTimes = $punchTimesCollection
                ->map(function ($time) {
                    return Carbon::parse($time)->format('H:i');
                })
                ->join(',');

            return [
                $actual->employee->biometric_number,
                $actual->employee->full_name,
                $actual->employee->current_branch->short_name,
                $actual->date,
                $punchCount,
                $punchTimes
            ];
        });
    }

    public function startCell(): string
    {
        return 'A2';
    }

    public function headings(): array
    {
        return [
            'Biometric Number',
            'Employee Name',
            'Branch',
            'Date',
            'Times',
            'Punches',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headings = $this->headings();
        $maxColIndex = count($headings) - 1;
        $lastColumnLetter = $this->getExcelColumnLetter($maxColIndex);
        $rowCount = $this->actual_logs->count() + 2;

        $sheet->mergeCells('A1:' . $lastColumnLetter . '1');
        $sheet->setCellValue('A1', 'Time Card');
        $sheet->getStyle('A1:' . $lastColumnLetter . '1')->applyFromArray([
            'font' => [
                'name' => 'Arial',
                'size' => 16,
                'bold' => true
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ]
            ]
        ]);

        $sheet->getStyle('A2:' . $lastColumnLetter . '2')->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Arial',
                'size' => 11
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ]
            ]
        ]);

        $sheet->getStyle('A2:' . $lastColumnLetter . $rowCount)->applyFromArray([
            'font' => [
                'name' => 'Arial',
                'size' => 11
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ]
            ]
        ]);

        for ($i = 0; $i <= $maxColIndex; $i++) {
            $colLetter = $this->getExcelColumnLetter($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
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
}
