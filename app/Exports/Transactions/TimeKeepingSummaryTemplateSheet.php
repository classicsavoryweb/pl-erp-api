<?php

namespace App\Exports\Transactions;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimeKeepingSummaryTemplateSheet implements FromCollection, WithStyles, WithCustomStartCell, WithTitle, WithStrictNullComparison
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
            $branchName = $summary->branch?->name ?: "-";

            $row = [
                $index + 1,
                $this->formatEmployeeId($employee?->employee_number ?? $employee?->biometric_number),
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
                        $row[] = 0.0;
                    }
                    continue;
                }

                if ($lateOverflow->count() > 0) {
                    $row[] = $this->formatDate($lateOverflow->first()->date);
                    $row[] = $this->formatMetric($lateOverflowMinutes);
                } else {
                    $row[] = $this->endDateLabel;
                    $row[] = 0.0;
                }
            }

            $row[] = $this->formatMetric($summary->tardiness);
            $row[] = $this->formatRemarks($summary->remarks);

            return $row;
        });

        $spacerRow = array_fill(0, self::TOTAL_COLUMNS, "");

        return collect($this->headerRows())
            ->push($spacerRow)
            ->merge($rows);
    }

    public function startCell(): string
    {
        return "A3";
    }

    public function title(): string
    {
        return "Sheet1";
    }

    private function formatMetric($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
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
            "No.", "ID NO.", "Branch", "Employee Name",
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
            "", "", "", "",
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
        $headerStartRow = 3;
        $headerEndRow = 4;
        $dataStartRow = 6;
        $lastRow = $dataStartRow + max($this->timekeeping_summary->count() - 1, 0);

        $sheet->mergeCells("A{$headerStartRow}:A{$headerEndRow}");
        $sheet->mergeCells("B{$headerStartRow}:B{$headerEndRow}");
        $sheet->mergeCells("C{$headerStartRow}:C{$headerEndRow}");
        $sheet->mergeCells("D{$headerStartRow}:D{$headerEndRow}");
        $sheet->mergeCells("AI{$headerStartRow}:AI{$headerEndRow}");
        $sheet->mergeCells("AJ{$headerStartRow}:AJ{$headerEndRow}");

        $lateGroupStartIndex = 16; 
        for ($i = 0; $i < self::LATE_SLOT_COUNT; $i++) {
            $start = $this->getExcelColumnLetter($lateGroupStartIndex + ($i * 2));
            $end = $this->getExcelColumnLetter($lateGroupStartIndex + ($i * 2) + 1);
            $sheet->mergeCells("{$start}{$headerStartRow}:{$end}{$headerStartRow}");
        }

        $sheet->freezePane("A6");

        $sheet->getStyle("A{$headerStartRow}:{$lastColumnLetter}{$headerEndRow}")->applyFromArray([
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
            $sheet->getStyle("A{$dataStartRow}:{$lastColumnLetter}{$lastRow}")->applyFromArray([
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

        $sheet->getStyle("A{$headerStartRow}:{$lastColumnLetter}{$lastRow}")->applyFromArray([
            "borders" => [
                "allBorders" => [
                    "borderStyle" => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    "color" => ["rgb" => "000000"],
                ],
            ],
        ]);

        if ($this->timekeeping_summary->count() > 0) {
            $sheet->getStyle("B{$dataStartRow}:B{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode("@");

            $accountingFormat = '#,##0.00;(#,##0.00);"-";@';
            $numericColumns = ["E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "R", "T", "V", "X", "Z", "AB", "AD", "AF", "AH", "AI"];
            foreach ($numericColumns as $col) {
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode($accountingFormat);
            }

            $sheet->getStyle("D{$dataStartRow}:D{$lastRow}")->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
            );
            $sheet->getStyle("AJ{$dataStartRow}:AJ{$lastRow}")->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
            );
            $sheet->getStyle("AJ{$dataStartRow}:AJ{$lastRow}")->getAlignment()->setWrapText(false);
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

        if ($lastRow >= $dataStartRow) {
            $sheet->getStyle("A{$dataStartRow}:{$lastColumnLetter}{$lastRow}")->applyFromArray([
                "alignment" => [
                    "horizontal" => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    "vertical" => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);

            for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(20);
            }
        }
    }
}
