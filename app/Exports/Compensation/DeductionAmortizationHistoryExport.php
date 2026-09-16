<?php

namespace App\Exports\Compensation;

use App\Models\DeductionHistory;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DeductionAmortizationHistoryExport implements
    FromCollection,
    WithHeadings,
    WithCustomStartCell,
    WithEvents
{
    public function __construct(
        private string $employeeNumber,
        private string $referenceNumber
    ) {}

    public function collection(): Collection
    {
        $employee = Employee::where(
            'employee_number',
            $this->employeeNumber
        )->firstOrFail();

        return DeductionHistory::with('deduction', 'employee')
            ->where('employee_id', $employee->id)
            ->where('reference_doc', $this->referenceNumber)
            ->orderBy('pay_date', 'DESC')
            ->get()
            ->map(function ($item) {

                return [
                    'pay_date' => $item->pay_date,
                    'amount' => $item->amount,
                    'new_balance' => $item->new_balance ,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Date Deducted',
            'Deducted Amount',
            'Running Balance',
        ];
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $employee = Employee::where(
                    'employee_number',
                    $this->employeeNumber
                )->firstOrFail();
                $history = DeductionHistory::with('deduction', 'actual_deduction')
                    ->where('employee_id', $employee->id)
                    ->where('reference_doc', $this->referenceNumber)
                    ->orderBy('pay_date', 'DESC')
                    ->first();
                $total_balance = $history?->new_balance;
                $name = $employee->last_name . ' ' . $employee->first_name;
                $description = $history?->deduction?->description ?? '';
                $total_amount = $history?->actual_deduction?->amount ?? '';
                $sheet->mergeCells('A1:C1');
                $sheet->setCellValue('A1',"Name:".$name);
                $sheet->mergeCells('A2:C2');
                $sheet->setCellValue('A2',"Deduction Type:".$description);
                $sheet->mergeCells('A3:C3');
                $sheet->setCellValue('A3',"Total Amount:".$total_amount);
                $sheet->mergeCells('A4:C4');
                $sheet->setCellValue('A4',"Remaining Balance:". number_format((float) $total_balance, 2));
                $sheet->getStyle('A1:D3')
                    ->getFont()
                    ->setBold(true);
                $sheet->getStyle('A5:C5')
                    ->getFont()
                    ->setBold(true);

                $sheet->getStyle('A5:C5')
                    ->getFill();
                $sheet->getStyle('A5:C5')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getColumnDimension('A')->setWidth(18);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(25);
                $sheet->getRowDimension(4)->setRowHeight(25);
                
            },
        ];
    }
}
