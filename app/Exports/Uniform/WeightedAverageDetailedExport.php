<?php

namespace App\Exports\Uniform;

use App\Models\WeightedAverage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class WeightedAverageDetailedExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private WeightedAverage $weightedAverage)
    {
    }

    public function collection()
    {
        return $this->weightedAverage->items->map(fn ($item) => [
            $this->weightedAverage->weighted_id,
            $this->formatDate($this->weightedAverage->date_posted),
            $this->formatDate($this->weightedAverage->date_from),
            $this->formatDate($this->weightedAverage->date_to),
            $this->weightedAverage->tag,
            $this->weightedAverage->status,
            $item->uniform_item_no,
            $item->description,
            $item->standard_uom,
            (float) $item->current_price,
            (float) $item->total_quantity,
            $item->standard_uom_two,
            (float) $item->total_amount,
            (float) $item->other_charges,
            (float) $item->new_standard_price,
            (float) $item->standard_difference,
            $item->reason,
            $this->weightedAverage->overall_remarks,
            $this->weightedAverage->prepared_by,
            $this->formatDate($this->weightedAverage->prepared_at, 'Y-m-d H:i:s'),
            $this->weightedAverage->reviewed_by,
            $this->formatDate($this->weightedAverage->reviewed_at, 'Y-m-d H:i:s'),
            $this->weightedAverage->posted_by,
            $this->formatDate($this->weightedAverage->posted_at, 'Y-m-d H:i:s'),
            $this->weightedAverage->cancelled_by,
            $this->formatDate($this->weightedAverage->cancelled_at, 'Y-m-d H:i:s'),
            $this->weightedAverage->cancel_reason,
        ]);
    }

    public function headings(): array
    {
        return [
            'WA Number',
            'Posting Date',
            'Date From',
            'Date To',
            'Tag',
            'Status',
            'Item Code',
            'Item Description',
            'Standard UOM',
            'Current Price',
            'Total Quantity',
            'Standard UOM 2',
            'Total Amount',
            'Other Charges',
            'New Standard Price',
            'Standard Difference',
            'Reason',
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
        return 'Weighted Average';
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
