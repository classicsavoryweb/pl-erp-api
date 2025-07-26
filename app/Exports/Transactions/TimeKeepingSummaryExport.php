<?php

namespace App\Exports\Transactions;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class TimeKeepingSummaryExport implements FromCollection, WithHeadings, WithStyles
{
    protected Collection $timekeeping;

    public function __construct(Collection $timekeeping)
    {
        $this->timekeeping = $timekeeping;
    }

    public function collection()
    {
        $maxLates = $this->timekeeping->max(function ($unique_timekeeping) {
            return count($unique_timekeeping->lates);
        });

        return $this->timekeeping->map(function ($unique_timekeeping, $index) use ($maxLates) {
            $data = [
                $index + 1,
                $unique_timekeeping->employee->biometric_number,
                $unique_timekeeping->branch->name,
                $unique_timekeeping->employee->full_name,
                $this->formatNumber($unique_timekeeping->regular_days),
                $this->formatNumber($unique_timekeeping->adjustment_amount),
                $this->formatNumber($unique_timekeeping->special_leave),
                $this->formatNumber($unique_timekeeping->cancelled_off),
                $this->formatNumber($unique_timekeeping->special_holiday),
                $this->formatNumber($unique_timekeeping->legal_holiday),
                $this->formatNumber($unique_timekeeping->regular_overtime),
                $this->formatNumber($unique_timekeeping->night_differential),
                $this->formatNumber($unique_timekeeping->night_differential_overtime),
                $this->formatNumber($unique_timekeeping->legal_holiday_overtime),
                $this->formatNumber($unique_timekeeping->special_holiday_overtime),
                $this->formatNumber($unique_timekeeping->undertime),
            ];

            $totalLateMins = $unique_timekeeping->tardiness;

            $lateColumns = [];
            $lateCount = count($unique_timekeeping->lates);

            for ($i = 0; $i < $maxLates; $i++) {
                if ($i < $lateCount) {
                    $late = $unique_timekeeping->lates[$i];
                    $lateColumns["late " . ($i + 1)] = $late->date . " / " . $late->late_minutes . " minutes";
                } else {
                    $lateColumns["late " . ($i + 1)] = "-";
                }
            }

            $data = array_merge($data, $lateColumns);

            $data[] = $totalLateMins;

            $data[] = $unique_timekeeping->remarks;

            return $data;
        });
    }

    private function formatNumber($value)
    {
        return $value == 0 ? '-' : number_format($value, 2, '.', '');
    }

    public function headings(): array
    {
        $headings = [
            'No',
            'Biometric Number',
            'Branch',
            'Employee Name',
            'Regular Days',
            'Taxable Adjustment Amount',
            'Special Leave Days',
            'Cancelled Off Hours',
            'Special Holiday Hours',
            'Legal Holiday Hours',
            'Regular Overtime Hours',
            'Night Diff Hours',
            'Night Diff Overtime Hours',
            'Legal Holiday Overtime Hours',
            'Special Holiday Overtime Hours',
            'Undertime Minutes',
        ];

        $maxLateCount = 0;
        foreach ($this->timekeeping as $unique_timekeeping) {
            $maxLateCount = max($maxLateCount, $unique_timekeeping->lates->count());
        }

        $lateHeadings = [];
        for ($i = 1; $i <= $maxLateCount; $i++) {
            $lateHeadings[] = "Late $i";
        }

        $lateHeadings[] = "Total Late Minutes";
        $headings = array_merge($headings, $lateHeadings);
        $headings[] = "Remarks";

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        $headings = $this->headings();

        $columns = range('A', chr(65 + count($headings) - 1)); 
        $maxLengths = array_fill_keys($columns, 0);

        $rows = $this->timekeeping;

        foreach ($rows as $index => $unique_timekeeping) {
            $rowNumber = $index + 2;

            $data = [
                $index + 1,
                $unique_timekeeping->employee->biometric_number,
                $unique_timekeeping->branch->name,
                $unique_timekeeping->employee->full_name,
                $unique_timekeeping->regular_days,
                $unique_timekeeping->adjustment_amount,
                $unique_timekeeping->special_leave,
                $unique_timekeeping->cancelled_off,
                $unique_timekeeping->special_holiday,
                $unique_timekeeping->legal_holiday,
                $unique_timekeeping->regular_overtime,
                $unique_timekeeping->night_differential,
                $unique_timekeeping->night_differential_overtime,
                $unique_timekeeping->legal_holiday_overtime,
                $unique_timekeeping->special_holiday_overtime,
                $unique_timekeeping->undertime,
            ];

            $totalLateMins = $unique_timekeeping->tardiness;
            $lateColumns = [];
            $lateCount = count($unique_timekeeping->lates);

            for ($i = 0; $i < $lateCount; $i++) {
                $late = $unique_timekeeping->lates[$i];
                $lateColumns["Late " . ($i + 1)] = $late->date . " / " . $late->late_minutes . " minutes";
            }

            for ($i = $lateCount; $i < count($headings) - 17 - 1; $i++) {
                $lateColumns["Late " . ($i + 1)] = "-";
            }

            $data = array_merge($data, $lateColumns);
            $data[] = $totalLateMins;
            $data[] = $unique_timekeeping->remarks;

            foreach ($columns as $colIndex => $col) {
                if (isset($data[$colIndex])) {
                    $cellValue = (string) $data[$colIndex];
                    $maxLengths[$col] = max($maxLengths[$col], strlen($cellValue)); 
                }
            }

            $sheet->getStyle('A' . $rowNumber . ':' . $columns[count($columns) - 1] . $rowNumber)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ]
                ]
            ]);

            if ($unique_timekeeping->employee->current_position->timekeeping_type == 'Monthly') {
                $sheet->getStyle('D' . $rowNumber)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '48c4cc']
                    ]
                ]);
            } elseif ($unique_timekeeping->employee->current_position->timekeeping_type == 'Daily') {
                $sheet->getStyle('D' . $rowNumber)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF']
                    ]
                ]);
            }
        }

        $sheet->getStyle('A1:' . $columns[count($columns) - 1] . '1')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ]
            ],
            'font' => [
                'bold' => true
            ],
        ]);

        for ($i = 0; $i < count($columns); $i++) {
            $col = $columns[$i];

            $headerCell = $sheet->getCell($col . '1');
            $headerLength = strlen($headerCell->getValue()) > 15 ? strlen($headerCell->getValue()) : 20;

            $maxContentLength = max($maxLengths[$col], $headerLength);

            $sheet->getColumnDimension($col)->setWidth($maxContentLength + 2);
        }
    }
}
