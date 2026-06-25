<?php

namespace App\Exports\Uniform;

use App\Models\UniformReplenishment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReplenishmentDetailedExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private UniformReplenishment $replenishment)
    {
    }

    public function collection()
    {
        return $this->replenishment->items->map(fn ($item) => [
            $this->replenishment->request_no,
            $this->formatDate($this->replenishment->requested_date),
            $this->replenishment->supplier?->vendor_number,
            $this->replenishment->supplier?->vendor_name,
            $this->replenishment->replenishment_type,
            $this->replenishment->status,
            $item->item?->item_no,
            $item->item?->description,
            $item->item?->standard_unit,
            (float) $item->requested_qty,
            (float) $item->approved_qty,
            (float) $item->received_qty,
            (float) $item->remaining_qty,
            (float) $item->unit_price,
            (float) $item->approved_qty * (float) $item->unit_price,
            $item->remarks,
            (float) $this->replenishment->total_amount,
            $this->replenishment->remarks,
            $this->replenishment->prepared_by,
            $this->formatDate($this->replenishment->created_at, 'Y-m-d H:i:s'),
            $this->replenishment->reviewed_by,
            $this->formatDate($this->replenishment->reviewed_at, 'Y-m-d H:i:s'),
            $this->replenishment->posted_by,
            $this->formatDate($this->replenishment->posted_at, 'Y-m-d H:i:s'),
            $this->replenishment->cancelled_by,
            $this->formatDate($this->replenishment->cancelled_at, 'Y-m-d H:i:s'),
            $this->replenishment->cancelled_reason,
        ]);
    }

    public function headings(): array
    {
        return [
            'Request Number',
            'Requested Date',
            'Supplier No.',
            'Supplier Name',
            'Replenishment Type',
            'Status',
            'Item Code',
            'Item Description',
            'Standard UOM',
            'Requested Quantity',
            'Approved Quantity',
            'Received Quantity',
            'Remaining Quantity',
            'Unit Price',
            'Item Total Amount',
            'Item Remarks',
            'Request Total Amount',
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
        return 'Replenishment';
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
