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

            $punches = "";

            if ($unique_timekeeping?->biotime_log?->punches) {
                foreach ($unique_timekeeping->biotime_log->punches as $punch) {
                    $time = Carbon::parse($punch->punch_time)->toTimeString();
                    $punches = $punches . $time . " | ";
                }
            }

            return [
                $index + 1,
                $unique_timekeeping->employee->full_name,
                $unique_timekeeping->employee->current_position->description,
                $unique_timekeeping->employee->biometric_number,
                $unique_timekeeping->date,
                $unique_timekeeping->schedule->timetable ?? "",
                $punches,
                $this->formatNumber($unique_timekeeping->worked_hours),
                $this->formatNumber($unique_timekeeping->final_worked_hours),
                $this->formatNumber($unique_timekeeping->tardiness),
                $this->formatNumber($unique_timekeeping->undertime),
                $this->formatNumber($unique_timekeeping->regular_overtime),
                $this->formatNumber($unique_timekeeping->night_diff_hours),
                $this->formatNumber($unique_timekeeping->night_diff_overtime),
                $this->formatNumber($unique_timekeeping->legal_holiday_hours),
                $this->formatNumber($unique_timekeeping->legal_holiday_overtime),
                $this->formatNumber($unique_timekeeping->special_holiday_hours),
                $this->formatNumber($unique_timekeeping->special_holiday_overtime),
                $this->formatNumber($unique_timekeeping->approved_overtime),
                $this->formatNumber($unique_timekeeping->travel_time),
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
            'Overtime',
            'Night Diff Hours',
            'Night Diff Overtime',
            'Legal Holiday Hours',
            'Legal Holiday Overtime',
            'Speical Holiday Hours',
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
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headings = $this->headings();
        $columns = range('A', 'T');
        $rowCount = $this->timekeeping->count() + 1;
        $sheet->getStyle('A1:T' . $rowCount)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('C2');

        $maxLengths = array_fill_keys($columns, 0);

        foreach ($columns as $colIndex => $col) {
            $maxLengths[$col] = strlen($headings[$colIndex]);
        }

        $sheet->getColumnDimension('B')->setAutoSize(true);

        $rows = $this->timekeeping;
        
        foreach ($rows as $index => $unique_timekeeping) {
            $punches = "";

            if ($unique_timekeeping?->biotime_log?->punches) {
                foreach ($unique_timekeeping->biotime_log->punches as $punch) {
                    $time = Carbon::parse($punch->punch_time)->toTimeString();
                    $punches = $punches . $time . " | "; 
                }
            }

            $rowNumber = $index + 2;
            $data = [
                $index + 1,
                $unique_timekeeping->employee->full_name,
                $unique_timekeeping->employee->current_position->description,
                $unique_timekeeping->employee->biometric_number,
                $unique_timekeeping->date,
                $unique_timekeeping->schedule->timetable ?? "",
                $punches,
                $unique_timekeeping->worked_hours,
                $unique_timekeeping->final_worked_hours,
                $unique_timekeeping->tardiness,
                $unique_timekeeping->undertime,
                $unique_timekeeping->regular_overtime,
                $unique_timekeeping->night_diff_hours,
                $unique_timekeeping->night_diff_overtime,
                $unique_timekeeping->legal_holiday_hours,
                $unique_timekeeping->legal_holiday_overtime,
                $unique_timekeeping->special_holiday_hours,
                $unique_timekeeping->special_holiday_overtime,
                $unique_timekeeping->approved_overtime,
                $unique_timekeeping->travel_time,
            ];

            foreach ($columns as $colIndex => $col) {
                $cellValue = (string) $data[$colIndex];
                $maxLengths[$col] = max($maxLengths[$col], strlen($cellValue));
            }

            if ($unique_timekeeping->employee->current_position->timekeeping_type == 'Monthly') {
                $sheet->getStyle('B' . $rowNumber)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '48c4cc']
                    ]
                ]);
            } elseif ($unique_timekeeping->employee->current_position->timekeeping_type == 'Daily') {
                $sheet->getStyle('B' . $rowNumber)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF']
                    ]
                ]);
            }

            $sheet->getStyle('A' . $rowNumber . ':T' . $rowNumber)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ]
                ]
            ]);
        }

        $sheet->getStyle('A1:T1')->applyFromArray([
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
