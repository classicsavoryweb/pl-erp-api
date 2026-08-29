<?php

namespace App\Exports\Compensation;

use App\Models\ConverterMain;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ConverterSampleExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithEvents
{
    protected Collection $transactions;
    protected $converterMainId;

    public function __construct(Collection $transactions, $converterMainId)
    {
        $this->transactions = $transactions;
        $this->converterMainId = $converterMainId;
    }

    public function title(): string {
        return "Converter Sample";
    }

    public function startCell(): string {
        return 'A6';
    }

    public function registerEvents(): array
    {

        $converter = ConverterMain::find($this->converterMainId);
        return [
            AfterSheet::class => function (AfterSheet $event) use($converter) {
                $sheet = $event->sheet->getDelegate();

                $companyName = env("COMPANY_NAME") == "SFI" ? 'SAVORY FASTFOOD INCORPORATED' : 'TONY TING FOOD CONCEPTS INC.';

                $sheet->setCellValue('A1', $companyName);
                $sheet->setCellValue('A2', 'Converter Number');
                $sheet->setCellValue('A3', 'Total Amount');
                $sheet->setCellValue('A4', 'Date Sent');

                $sheet->setCellValue('B2', $converter->batch_number);
                $sheet->setCellValue('B3', number_format($converter->total_amount, 2));
                $sheet->setCellValue('B4', Carbon::parse($converter->sent_at)->toDateString());
            },
        ];
    }

    public function collection()
    {
        return $this->transactions->map(function ($transaction) {
            return [
                $transaction->bank_account_number ?? "",
                $transaction->amount,
                $transaction->employee->full_name,
                "OK",
                $transaction->transaction_type->description ?? "",
            ];
        });
    }

    public function headings(): array
    {
        return [
            "ACCOUNT #",
            "AMOUNT",
            "NAME",
            "STATUS",
            "REMARKS"
        ];
    }
}
