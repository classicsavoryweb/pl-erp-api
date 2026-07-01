<?php

namespace App\Exports\Uniform;

use App\Models\UniformPhysicalCount;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PhysicalCountDetailedExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private UniformPhysicalCount $physicalCount)
    {
    }

    public function collection()
    {
        return $this->physicalCount->items->map(fn ($item) => [
            $this->physicalCount->physical_count_no,
            $item->item_no,
            $item->description,
            $item->uom,
            (float) $item->beginning_inventory,
            (float) $item->total_purchases,
            (float) $item->total_outgoing,
            (float) $item->should_be_inventory,
            (float) $item->physical_count,
            $item->item_remarks,
            optional($this->physicalCount->posted_at)->format('Y-m-d'),
            $this->physicalCount->status,
            $this->physicalCount->posted_by,
            optional($this->physicalCount->begin_date)->format('Y-m-d'),
            optional($this->physicalCount->ending_date)->format('Y-m-d'),
            $this->physicalCount->counted_by,
            $this->physicalCount->remarks,
        ]);
    }

    public function headings(): array
    {
        return [
            'PC Number',
            'Item Code',
            'Item Description',
            'Standard UOM',
            'Beginning Inventory',
            'Total Purchases',
            'Total Outgoing',
            'Should be Inventory',
            'Physical Count',
            'Item Remarks',
            'Posting Date',
            'Status',
            'Posted By',
            'Date of Beginning Count',
            'Date of Ending Count',
            'Counted By',
            'Overall Remarks',
        ];
    }

    public function title(): string
    {
        return 'Physical Count';
    }
}
