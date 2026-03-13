<?php

namespace App\Exports\Transactions;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimeKeepingSummaryTemplateSheet implements FromCollection, WithStyles
{
    private const LATE_SLOT_COUNT = 9;
    private const TOTAL_COLUMNS = 36; 

    protected Collection $timekeeping_summary;
    protected $start_date;
    protected $end_date;
    protected string $endDateLabel;

    public function __construct(Collection $timekeeping_summary, $start_date, $end_date)
    {
        $this->timekeeping_summary = $timekeeping_summary;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->endDateLabel = $this->formatDate($end_date);
    }

    public function collection()
    {
        $rows = $this->timekeeping_summary->values()->map(function ($summary, $index) {
            $employee = $summary->employee;
            $branchName = $summary->branch?->short_name ?: ($summary->branch?->name ?: "-");

            $row = [
                $index + 1,
                $this->formatEmployeeId($employee?->biometric_number ?? $employee?->employee_number),
                $branchName,
                $employee?->full_name ?? "-",
                $this->formatMetric($summary->regular_days),
                $this->formatMetric($summary->adjustment_amount),
                $this->formatMetric($summary->special_leave),
                $this->formatMetric($summary->cancelled_off),
                $this->formatMetric($summary->special_holiday),
                $this->formatMetric($summary->legal_holiday),
                $this->formatMetric($summary->regular_overtime),
                $this->formatMetric($summary->night_differential),
                $this->formatMetric($summary->night_differential_overtime),
                $this->formatMetric($summary->legal_holiday_overtime),
                $this->formatMetric($summary->special_holiday_overtime),
                $this->formatMetric($summary->undertime),
            ];

            $lates = collect($summary->lates ?? [])->sortBy("date")->values();
            $lateOverflowStartIndex = self::LATE_SLOT_COUNT - 1;
            $lateOverflow = $lates->slice($lateOverflowStartIndex)->values();
            $lateOverflowMinutes = (float) $lateOverflow->sum(function ($late) {
                return (float) ($late->late_minutes ?? 0);
            });

            for ($i = 0; $i < self::LATE_SLOT_COUNT; $i++) {
                if ($i < $lateOverflowStartIndex) {
                    $late = $lates->get($i);
                    if ($late) {
                        $row[] = $this->formatDate($late->date);
                        $row[] = $this->formatMetric($late->late_minutes);
                    } else {
                        $row[] = $this->endDateLabel;
                        $row[] = "-";
                    }
                    continue;
                }

                if ($lateOverflow->count() > 0) {
                    $row[] = $this->formatDate($lateOverflow->first()->date);
                    $row[] = $this->formatMetric($lateOverflowMinutes);
                } else {
                    $row[] = $this->endDateLabel;
                    $row[] = "-";
                }
            }

            $row[] = $this->formatMetric($summary->tardiness);
            $row[] = $this->formatRemarks($summary->remarks);

            return $row;
        });

        return collect($this->headerRows())->merge($rows);
    }

    private function formatMetric($value): string
    {
        if ($value === null || $value === '' || (float) $value == 0.0) {
            return "-";
        }

        return number_format((float) $value, 1, ".", "");
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return "-";
        }

        try {
            return Carbon::parse($value)->format("m/d/Y");
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatEmployeeId($value): string
    {
        $id = trim((string) $value);
        if ($id === "") {
            return "-";
        }

        if (ctype_digit($id) && strlen($id) < 7) {
            $id = str_pad($id, 7, "0", STR_PAD_LEFT);
        }

        return $id;
    }

    private function formatRemarks($value): string
    {
        $remarks = trim((string) $value);
        if ($remarks === "") {
            return "-";
        }

        $remarks = str_replace(["\r\n", "\r", "\n"], " ", $remarks);
        $remarks = preg_replace('/\s*\|\s*/', ' | ', $remarks);
        $remarks = preg_replace('/\s+/', ' ', $remarks);

        return trim($remarks);
    }

    private function headerRows(): array
    {
        $firstHeaderRow = [
            "", "", "", "Employee Name",
            "Regular Days", "Taxable Adjustment", "Special Leave", "Cancelled Off",
            "Special Holiday", "Legal Holiday", "OT REG.", "Night Diff. Regular",
            "Night Diff. OT", "Legal Holiday OT", "Special Holiday OT", "Undertime",
        ];

        for ($i = 1; $i <= self::LATE_SLOT_COUNT; $i++) {
            $firstHeaderRow[] = "Late {$i}";
            $firstHeaderRow[] = "";
        }

        $firstHeaderRow[] = "Total Mins";
        $firstHeaderRow[] = "Remarks Column";

        $secondHeaderRow = [
            "No.", "ID NO.", "Branch", "",
            "Days", "Amount", "Days", "Hours",
            "Hours", "Hours", "Hours", "Hours",
            "Hours", "Hours", "Hours", "Mins.",
        ];

        for ($i = 1; $i <= self::LATE_SLOT_COUNT; $i++) {
            $secondHeaderRow[] = "Date (MM/DD/YYYY)";
            $secondHeaderRow[] = "Mins";
        }

        $secondHeaderRow[] = "";
        $secondHeaderRow[] = "";

        return [$firstHeaderRow, $secondHeaderRow];
    }

    private function getExcelColumnLetter(int $colIndex): string
    {
        $colName = "";
        while ($colIndex >= 0) {
            $colName = chr($colIndex % 26 + 65) . $colName;
            $colIndex = floor($colIndex / 26) - 1;
        }
        return $colName;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumnLetter = $this->getExcelColumnLetter(self::TOTAL_COLUMNS - 1); // AJ
        $dataStartRow = 3;
        $lastRow = $dataStartRow + max($this->timekeeping_summary->count() - 1, 0);

        $sheet->mergeCells("A1:A2");
        $sheet->mergeCells("B1:B2");
        $sheet->mergeCells("C1:C2");
        $sheet->mergeCells("D1:D2");
        $sheet->mergeCells("AI1:AI2");
        $sheet->mergeCells("AJ1:AJ2");

        $lateGroupStartIndex = 16; 
        for ($i = 0; $i < self::LATE_SLOT_COUNT; $i++) {
            $start = $this->getExcelColumnLetter($lateGroupStartIndex + ($i * 2));
            $end = $this->getExcelColumnLetter($lateGroupStartIndex + ($i * 2) + 1);
            $sheet->mergeCells("{$start}1:{$end}1");
        }

        $sheet->freezePane("A3");

        $sheet->getStyle("A1:{$lastColumnLetter}2")->applyFromArray([
            "font" => [
                "bold" => true,
                "name" => "Aptos Narrow",
                "size" => 11,
            ],
            "alignment" => [
                "horizontal" => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                "vertical" => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                "wrapText" => true,
            ],
            "fill" => [
                "fillType" => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                "color" => ["rgb" => "D3D3D3"],
            ],
        ]);

        if ($this->timekeeping_summary->count() > 0) {
            $sheet->getStyle("A3:{$lastColumnLetter}{$lastRow}")->applyFromArray([
                "font" => [
                    "name" => "Aptos Narrow",
                    "size" => 11,
                ],
                "alignment" => [
                    "horizontal" => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    "vertical" => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    "wrapText" => false,
                ],
            ]);
        }

        $sheet->getStyle("A1:{$lastColumnLetter}{$lastRow}")->applyFromArray([
            "borders" => [
                "allBorders" => [
                    "borderStyle" => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    "color" => ["rgb" => "000000"],
                ],
            ],
        ]);

        if ($this->timekeeping_summary->count() > 0) {
            $sheet->getStyle("B3:B{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode("@");

            $sheet->getStyle("D3:D{$lastRow}")->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
            );
            $sheet->getStyle("AJ3:AJ{$lastRow}")->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
            );
            $sheet->getStyle("AJ3:AJ{$lastRow}")->getAlignment()->setWrapText(false);
        }

        for ($i = 0; $i < self::TOTAL_COLUMNS; $i++) {
            $colLetter = $this->getExcelColumnLetter($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $sheet->getColumnDimension("D")->setWidth(38);
        $sheet->getColumnDimension("AJ")->setWidth(70);

        $lateDateStart = 16; // Q
        for ($i = 0; $i < self::LATE_SLOT_COUNT; $i++) {
            $dateCol = $this->getExcelColumnLetter($lateDateStart + ($i * 2));
            $minsCol = $this->getExcelColumnLetter($lateDateStart + ($i * 2) + 1);
            $sheet->getColumnDimension($dateCol)->setWidth(14);
            $sheet->getColumnDimension($minsCol)->setWidth(8);
        }

        if ($lastRow >= 3) {
            $sheet->getStyle("A3:{$lastColumnLetter}{$lastRow}")->applyFromArray([
                "alignment" => [
                    "horizontal" => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    "vertical" => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);

            for ($row = 3; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(20);
            }
        }
    }
}
