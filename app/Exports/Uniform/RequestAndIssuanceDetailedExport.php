<?php

namespace App\Exports\Uniform;

use App\Models\RequestAndIssuance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RequestAndIssuanceDetailedExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private RequestAndIssuance $issuance)
    {
    }

    public function collection()
    {
        return $this->issuance->items->map(fn ($item) => [
            $this->issuance->request_number,
            $this->formatDate($this->issuance->date_requested),
            $this->formatDate($this->issuance->date_posted),
            $this->issuance->employee_id,
            $this->issuance->employee_name,
            $this->issuance->branch,
            $this->issuance->position,
            $this->formatDate($this->issuance->date_hired),
            $this->issuance->issuance_type,
            $this->issuance->status,
            $this->issuance->sent_email,
            $this->issuance->payroll_entry,
            $this->formatDate($this->issuance->payroll_sent_date),
            $item->uniform_item_no,
            $item->description,
            $item->size,
            $item->gender,
            (float) $item->stock_on_hand,
            (float) $item->requested_quantity,
            (float) $item->remaining_quantity,
            $item->uom,
            (float) $item->unit_price,
            (float) $item->total_amount,
            $item->item_remarks,
            (float) $this->issuance->total_amount,
            $this->issuance->created_by,
            $this->formatDate($this->issuance->created_at, 'Y-m-d H:i:s'),
            $this->issuance->reviewed_by,
            $this->formatDate($this->issuance->date_reviewed, 'Y-m-d H:i:s'),
            $this->issuance->posted_by,
            $this->formatDate($this->issuance->date_posted, 'Y-m-d H:i:s'),
            $this->issuance->cancelled_by,
            $this->formatDate($this->issuance->date_cancelled, 'Y-m-d H:i:s'),
            $this->issuance->cancel_reason,
        ]);
    }

    public function headings(): array
    {
        return [
            'Request Number',
            'Date Requested',
            'Date Posted',
            'Employee ID',
            'Employee Name',
            'Branch',
            'Position',
            'Date Hired',
            'Issuance Type',
            'Status',
            'Sent Email',
            'Payroll Entry',
            'Payroll Sent Date',
            'Item Code',
            'Item Description',
            'Size',
            'Gender',
            'Stock On Hand',
            'Requested Quantity',
            'Remaining Quantity',
            'UOM',
            'Unit Price',
            'Item Total Amount',
            'Item Remarks',
            'Request Total Amount',
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
        return 'Request Issuance';
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
