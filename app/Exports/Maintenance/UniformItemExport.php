<?php

namespace App\Exports\Maintenance;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UniformItemExport implements FromCollection, WithHeadings
{
    protected Collection $items;

    public function __construct(Collection $items)
    {
        $this->items = $items;
    }

    public function collection()
    {
        return $this->items->map(function ($item) {
            return [
                $item->item_no,
                $item->description,
                $item->size?->description,
                $item->gender?->description,
                $item->standard_unit_price,
                $item->stock_on_hand,
                $item->status,
                $item->standard_qty,
                $item->standard_unit,
                $item->min_order,
                $item->order_limit,
                $item->crit_level,
                $item->minimum_rpl,
                $item->free_uniform ? 'Yes' : 'No',
                $item->made_to_order ? 'Yes' : 'No',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Item Code',
            'Description',
            'Size',
            'Gender',
            'Standard Price',
            'Stock on Hand',
            'Status',
            'Standard Packaging Qty',
            'Standard Packaging Unit',
            'Minimum Order',
            'Ordering Limit',
            'Critical Level',
            'Minimum Replenishment',
            'Applicable for Free Uniform',
            'Made to Order',
        ];
    }
}
