<?php

namespace App\Exports\Transactions;

use Carbon\Carbon;
use Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TimeKeepingExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting
{
    protected Collection $timekeeping;

    public function __construct(Collection $timekeeping)
    {
        $this->timekeeping = $timekeeping;
    }

    public function collection()
    {
        return $this->timekeeping->map(function ($unique_timekeeping, $index): array {
            $employee = $unique_timekeeping->employee;
            $timekeeping = $unique_timekeeping->timekeeping;

            $punches = "";

            if ($timekeeping?->biotime_log?->punches) {
                foreach ($timekeeping->biotime_log->punches as $punch) {
                    $time = Carbon::parse($punch->punch_time)->toTimeString();
                    $punches = $punches . $time . " | ";
                }
            }

            return [
                $index + 1,
                $employee?->full_name ?? "",
                $employee?->current_position?->description ?? "",
                $employee?->biometric_number ?? "",
                $unique_timekeeping->date,
                $unique_timekeeping->timetable ?? "",
                $punches,
                $this->formatNumber($timekeeping?->worked_hours),
                $this->formatNumber($timekeeping?->final_worked_hours),
                $this->formatNumber($timekeeping?->tardiness),
                $this->formatNumber($timekeeping?->undertime),
                $this->formatNumber($timekeeping?->cancelled_off_hours),
                $this->formatNumber($timekeeping?->cancelled_off_overtime),
                $this->formatNumber($timekeeping?->regular_overtime),
                $this->formatNumber($timekeeping?->night_diff_hours),
                $this->formatNumber($timekeeping?->night_diff_overtime),
                $this->formatNumber($timekeeping?->legal_holiday_hours),
                $this->formatNumber($timekeeping?->legal_holiday_overtime),
                $this->formatNumber($timekeeping?->special_holiday_hours),
                $this->formatNumber($timekeeping?->special_holiday_overtime),
                $this->formatNumber($timekeeping?->approved_overtime),
                $this->formatNumber($timekeeping?->travel_time),
            ];
        });
    }

    private function formatNumber($value)
    {
        return $value == 0 ? '' : number_format((float)$value, 2, '.', '');
    }

    public function headings(): array
    {
        return [
            'No',
            'Employee Name',
            'Position',
            'Biometric Number',
            'Date',
            'Schedule',
            'Punches',
            'Actual Worked Hours',
            'Final Worked Hours',
            'Tardiness',
            'Undertime',
            'Cancelled Off Hours',
            'Cancelled Off Overtime',
            'Overtime',
            'Night Diff Hours',
            'Night Diff Overtime',
            'Legal Holiday Hours',
            'Legal Holiday Overtime',
            'Special Holiday Hours',
            'Special Holiday Overtime',
            'Approved Overtime',
            'Travel Time',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => NumberFormat::FORMAT_NUMBER_0,
            'I' => NumberFormat::FORMAT_NUMBER_0,
            'J' => NumberFormat::FORMAT_NUMBER_0,
            'K' => NumberFormat::FORMAT_NUMBER_0,
            'L' => NumberFormat::FORMAT_NUMBER_0,
            'M' => NumberFormat::FORMAT_NUMBER_0,
            'N' => NumberFormat::FORMAT_NUMBER_0,
            'O' => NumberFormat::FORMAT_NUMBER_0,
            'P' => NumberFormat::FORMAT_NUMBER_0,
            'Q' => NumberFormat::FORMAT_NUMBER_0,
            'R' => NumberFormat::FORMAT_NUMBER_0,
            'S' => NumberFormat::FORMAT_NUMBER_0,
            'T' => NumberFormat::FORMAT_NUMBER_0,
            'U' => NumberFormat::FORMAT_NUMBER_0,
            'V' => NumberFormat::FORMAT_NUMBER_0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headings = $this->headings();
        $columns = range('A', 'V');
        $rowCount = $this->timekeeping->count() + 1;
        $sheet->getStyle('A1:V' . $rowCount)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('C2');

        $maxLengths = array_fill_keys($columns, 0);

        foreach ($columns as $colIndex => $col) {
            $maxLengths[$col] = strlen($headings[$colIndex]);
        }

        $sheet->getColumnDimension('B')->setAutoSize(true);

        $rows = $this->timekeeping;

        foreach ($rows as $index => $unique_timekeeping) {
            $employee = $unique_timekeeping->employee;
            $timekeeping = $unique_timekeeping->timekeeping;
            $punches = "";

            if ($timekeeping?->biotime_log?->punches) {
                foreach ($timekeeping->biotime_log->punches as $punch) {
                    $time = Carbon::parse($punch->punch_time)->toTimeString();
                    $punches = $punches . $time . " | "; 
                }
            }

            $rowNumber = $index + 2;
            $data = [
                $index + 1,
                $employee?->full_name ?? "",
                $employee?->current_position?->description ?? "",
                $employee?->biometric_number ?? "",
                $unique_timekeeping->date,
                $unique_timekeeping->timetable ?? "",
                $punches,
                $timekeeping?->worked_hours,
                $timekeeping?->final_worked_hours,
                $timekeeping?->tardiness,
                $timekeeping?->undertime,
                $timekeeping?->cancelled_off_hours,
                $timekeeping?->cancelled_off_overtime,
                $timekeeping?->regular_overtime,
                $timekeeping?->night_diff_hours,
                $timekeeping?->night_diff_overtime,
                $timekeeping?->legal_holiday_hours,
                $timekeeping?->legal_holiday_overtime,
                $timekeeping?->special_holiday_hours,
                $timekeeping?->special_holiday_overtime,
                $timekeeping?->approved_overtime,
                $timekeeping?->travel_time,
            ];

            foreach ($columns as $colIndex => $col) {
                $cellValue = (string) $data[$colIndex];
                $maxLengths[$col] = max($maxLengths[$col], strlen($cellValue));
            }

            if (($employee?->current_position?->timekeeping_type ?? null) == 'Monthly') {
                $sheet->getStyle('B' . $rowNumber)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '48c4cc']
                    ]
                ]);
            } elseif (($employee?->current_position?->timekeeping_type ?? null) == 'Daily') {
                $sheet->getStyle('B' . $rowNumber)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF']
                    ]
                ]);
            }

            $sheet->getStyle('A' . $rowNumber . ':V' . $rowNumber)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ]
                ]
            ]);
        }

        $sheet->getStyle('A1:V1')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ]
            ],
            'font' => [
                'bold' => true
            ]
        ]);

        foreach ($columns as $col) {
            $sheet->getColumnDimension($col)->setWidth($maxLengths[$col] + 2);
        }
    }
}
