<?php

namespace App\Exports\Uniform;

use App\Models\UniformReceiving;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReceivingDetailedExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private UniformReceiving $receiving)
    {
    }

    public function collection()
    {
        return $this->receiving->items->map(fn ($item) => [
            $this->receiving->receiving_no,
            $this->receiving->replenishment?->request_no,
            $this->formatDate($this->receiving->date_received),
            $this->receiving->supplier?->vendor_number,
            $this->receiving->supplier?->vendor_name,
            $this->receiving->crf_no,
            $this->receiving->document_no,
            $this->receiving->checker_name,
            $this->receiving->transmitted_by,
            $this->receiving->status,
            $item->item?->item_no,
            $item->item?->description,
            $item->item?->standard_unit,
            (float) $item->quantity,
            (float) $item->unit_price,
            (float) $item->quantity * (float) $item->unit_price,
            $item->remarks,
            (float) $this->receiving->total_amount,
            $this->receiving->remarks,
            $this->receiving->prepared_by,
            $this->formatDate($this->receiving->created_at, 'Y-m-d H:i:s'),
            $this->receiving->reviewed_by,
            $this->formatDate($this->receiving->reviewed_at, 'Y-m-d H:i:s'),
            $this->receiving->posted_by,
            $this->formatDate($this->receiving->posted_at, 'Y-m-d H:i:s'),
            $this->receiving->cancelled_by,
            $this->formatDate($this->receiving->cancelled_at, 'Y-m-d H:i:s'),
            $this->receiving->cancelled_reason,
        ]);
    }

    public function headings(): array
    {
        return [
            'Receiving Number',
            'Replenishment Number',
            'Date Received',
            'Supplier No.',
            'Supplier Name',
            'CRF No.',
            'Document No.',
            'Checker',
            'Transmitted By',
            'Status',
            'Item Code',
            'Item Description',
            'Standard UOM',
            'Quantity',
            'Unit Price',
            'Item Total Amount',
            'Item Remarks',
            'Receiving Total Amount',
            'Overall Remarks',
            'Prepared By',
            'Prepared Date',
            'Reviewed By',
            'Reviewed Date',
            'Posted By',
            'Posted Date',
            'Cancelled By',
            'Cancelled Date',
            'Cancel Reason',
        ];
    }

    public function title(): string
    {
        return 'Receiving';
    }

    private function formatDate(mixed $value, string $format = 'Y-m-d'): ?string
    {
        if (!$value) {
            return null;
        }

        return is_object($value) && method_exists($value, 'format')
            ? $value->format($format)
            : date($format, strtotime((string) $value));
    }
}
