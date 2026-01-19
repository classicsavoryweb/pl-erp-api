<?php

namespace App\Exports\Transactions;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;

class TimeKeepingSummaryExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, WithCustomStartCell
{
    protected Collection $timekeeping_summary;
    protected $start_date;
    protected $end_date;

    public function __construct(Collection $timekeeping_summary, $start_date, $end_date)
    {
        $this->timekeeping_summary = $timekeeping_summary;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function collection()
    {
        $startDate = $this->start_date;
        $endDate = $this->end_date;

        $maxLates = $this->timekeeping_summary->max(function ($unique_timekeeping) {
            return count($unique_timekeeping->lates ?? []);
        });

        $maxUndertimes = $this->timekeeping_summary->max(function ($unique_timekeeping) use ($startDate, $endDate) {
            return collect($unique_timekeeping->employee?->timekeeping ?? [])
                ->filter(function ($tk) use ($startDate, $endDate) {
                    return
                        Carbon::parse($tk->date)->between(
                            Carbon::parse($startDate),
                            Carbon::parse($endDate)
                        ) &&
                        $tk->undertime > 0;
                })
                ->count();
        });

        return $this->timekeeping_summary->map(function ($unique_timekeeping, $index) use ($maxLates, $maxUndertimes, $startDate, $endDate) {
            $data = [
                $index + 1,
                $unique_timekeeping->employee->biometric_number,
                $unique_timekeeping->branch->name,
                $unique_timekeeping->employee->current_branch->name,
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
            $data[] = $totalLateMins; 

            $undertimeList = collect($unique_timekeeping->employee?->timekeeping ?? [])
            ->filter(fn ($ut) =>
                $ut->undertime > 0 &&
                Carbon::parse($ut->date)->between(
                    Carbon::parse($startDate),
                    Carbon::parse($endDate)
                )
            )
            ->values();

            $undertimeColumns = [];
            for ($i = 0; $i < $maxUndertimes; $i++) {
                if ($i < $undertimeList->count()) {
                    $ut = $undertimeList[$i];
                    $minutes = $ut->undertime;
                    $undertimeColumns["undertime " . ($i + 1)] =
                        $ut->date . " / " . $minutes . " " . ($minutes == 1 ? "minute" : "minutes");
                } else {
                    $undertimeColumns["undertime " . ($i + 1)] = "";
                }
            }
            $data = array_merge($data, $undertimeColumns);

            $lateColumns = [];
            $lateCount = count($unique_timekeeping->lates ?? []);
            for ($i = 0; $i < $maxLates; $i++) {
                if ($i < $lateCount) {
                    $late = $unique_timekeeping->lates[$i];
                    $lateMinutes = $late->late_minutes;
                    $lateColumns["late " . ($i + 1)] =
                        $late->date . " / " . $lateMinutes . " " . ($lateMinutes == 1 ? "minute" : "minutes");
                } else {
                    $lateColumns["late " . ($i + 1)] = "";
                }
            }
            $data = array_merge($data, $lateColumns);

            $data[] = $unique_timekeeping->remarks;

            return $data;
        });
    }

    private function formatNumber($value)
    {
        return $value == 0 ? '' : number_format($value, 2, '.', '');
    }

    public function headings(): array
    {
        $headings = [
            'No',
            'Biometric Number',
            'Timekeeping Branch',
            'Home Branch',
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
            'Total Late Minutes'
        ];

        $maxUndertimeCount = $this->timekeeping_summary->max(function ($unique_timekeeping) {
            return collect($unique_timekeeping->employee->timekeeping ?? [])
                ->filter(fn($ut) => $ut->undertime > 0)
                ->count();
        });

        $undertimeHeadings = [];
        for ($i = 1; $i <= $maxUndertimeCount; $i++) {
            $undertimeHeadings[] = "Undertime $i";
        }

        $maxLateCount = $this->timekeeping_summary->max(function ($unique_timekeeping) {
            return count($unique_timekeeping->lates ?? []);
        });

        $lateHeadings = [];
        for ($i = 1; $i <= $maxLateCount; $i++) {
            $lateHeadings[] = "Late $i";
        }

        $headings = array_merge($headings, $undertimeHeadings, $lateHeadings);
        $headings[] = "Remarks";

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
    
    public function columnFormats(): array
    {
        $formats = [];
        $headings = $this->headings();
        $numericHeadings = [
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
            'Total Late Minutes',
        ];
        foreach ($headings as $idx => $heading) {
            $colLetter = $this->getExcelColumnLetter($idx);
            if (in_array($heading, $numericHeadings) || preg_match('/^Late \\d+$/', $heading)) {
                $formats[$colLetter] = NumberFormat::FORMAT_NUMBER_0;
            }
        }
        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        $headings = $this->headings();

        $maxColIndex = count($headings) - 1;
        $lastColumnLetter = $this->getExcelColumnLetter($maxColIndex);

        $headerRow     = 3;
        $dataStartRow  = $headerRow + 1;
        $dataCount     = $this->timekeeping_summary->count();
        $lastRow       = $dataStartRow + max($dataCount - 1, 0);

        $sheet->getStyle('A' . $headerRow . ':' . $lastColumnLetter . $lastRow)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $columns = [];
        for ($i = 0; $i <= $maxColIndex; $i++) {
            $columns[] = $this->getExcelColumnLetter($i);
        }

        $sheet->freezePane('F2');

        $maxLengths = array_fill_keys($columns, 0);
        $rows = $this->timekeeping_summary;

        $maxLateColumns = $this->timekeeping_summary->max(function ($unique_timekeeping) {
            return count($unique_timekeeping->lates);
        });

        $maxUndertimeColumns = $this->timekeeping_summary->max(function ($unique_timekeeping) {
            return count($unique_timekeeping->employee->timekeeping);
        });

        foreach ($rows as $index => $unique_timekeeping) {

            $rowNumber = $dataStartRow + $index;
            $data = [
                $index + 1,
                $unique_timekeeping->employee->biometric_number,
                $unique_timekeeping->branch->name,
                $unique_timekeeping->employee->current_branch->name,
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

            $lateColumnsData = [];
            $actualLateCount = count($unique_timekeeping->lates);

            for ($i = 0; $i < $actualLateCount; $i++) {
                $late = $unique_timekeeping->lates[$i];
                $lateMinutes = $late->late_minutes;
                $lateColumnsData[] = $late->date . " / " . $lateMinutes . " " . ($lateMinutes == 1 ? "minute" : "minutes");
            }

            for ($i = $actualLateCount; $i < $maxLateColumns; $i++) {
                $lateColumnsData[] = "-";
            }

            $undertimeColumnsData = [];
            $actualUndertimeCount = count($unique_timekeeping->employee->timekeeping ?? []);

            for ($i = 0; $i < $actualUndertimeCount; $i++) {
                $undertime = $unique_timekeeping->employee->timekeeping[$i];
                $undertimeMinutes = $undertime->undertime;
                $undertimeColumnsData[] = $undertime->date . " / " .  $undertimeMinutes . " " . ($undertimeMinutes == 1 ? "minute" : "minutes") ?? ''; 
            }

            for ($i = $actualUndertimeCount; $i < $maxUndertimeColumns; $i++) {
                $undertimeColumnsData[] = "-";
            }

            $data = array_merge($data, $lateColumnsData, $undertimeColumnsData);
            $data[] = $unique_timekeeping->tardiness;
            $data[] = $unique_timekeeping->remarks;

            foreach ($columns as $colIndex => $col) {
                if (isset($data[$colIndex])) {
                    $cellValue = (string) $data[$colIndex];
                    $maxLengths[$col] = max($maxLengths[$col], strlen($cellValue));
                }
            }

            foreach ($columns as $colIndex => $col) {
                $headerCell = $sheet->getCell($col . $headerRow);
                $headerLength = strlen((string) $headerCell->getValue());

                $maxContentLength = max($maxLengths[$col], $headerLength);

                $sheet->getColumnDimension($col)->setWidth($maxContentLength + 2);
            }

            $sheet->getColumnDimension('E')->setAutoSize(true);

            $sheet->getStyle('A' . $rowNumber . ':' . $lastColumnLetter . $rowNumber)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ]
                ]
            ]);

            if (isset($unique_timekeeping->employee->current_position->timekeeping_type)) {
                if ($unique_timekeeping->employee->current_position->timekeeping_type == 'Monthly') {
                    $sheet->getStyle('E' . $rowNumber)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '48c4cc']
                        ]
                    ]);
                } elseif ($unique_timekeeping->employee->current_position->timekeeping_type == 'Daily') {
                    $sheet->getStyle('E' . $rowNumber)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF']
                        ]
                    ]);
                }
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


        for ($i = 0; $i < $maxUndertimeColumns; $i++) {
            $colLetter = $this->getExcelColumnLetter(18 + $i);
            $sheet->getStyle($colLetter . $headerRow . ':' . $colLetter . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ]
                ],
            ]);
        }
    }
}
