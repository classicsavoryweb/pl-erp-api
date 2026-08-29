<?php

namespace App\Exports\Uniform;

use App\Models\UniformVariance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class VarianceReportDetailedExport implements WithMultipleSheets
{
    public function __construct(private UniformVariance $variance)
    {
    }

    public function sheets(): array
    {
        return [
            new UniformArraySheet('Variance', $this->varianceHeadings(), $this->varianceRows()),
            $this->receivingSheet('Purchasing Department', 'Purchasing Department'),
            $this->receivingSheet('Made to Order', 'Made to Order'),
            $this->issuanceSheet('ATD', 'atd'),
            $this->issuanceSheet('Free Uniform', 'free'),
        ];
    }

    private function varianceHeadings(): array
    {
        return [
            'Item Code',
            'Item Description',
            'Standard UOM',
            'Beginning Inventory',
            'Total Purchases (Purchasing Department)',
            'Total Purchases (Made to Order)',
            'Total Issuance (ATD)',
            'Total Issuance (Free Uniform)',
            'Should be Inventory',
            'Physical Count',
            'Variance',
            'Adjustment',
            'Final Variance',
            'Standard Cost',
            'Total Variance Cost',
            'Reason of Adjustment',
        ];
    }

    private function varianceRows(): array
    {
        return $this->variance->items->map(fn ($item) => [
            $item->item_no,
            $item->description,
            $item->uom,
            (float) $item->beginning_inventory,
            (float) $item->purchasing_department,
            (float) $item->made_to_order,
            (float) $item->atd,
            (float) $item->free_uniform,
            (float) $item->should_be_inventory,
            (float) $item->physical_count,
            (float) $item->variance,
            (float) $item->adjustment,
            (float) $item->final_variance,
            (float) $item->standard_cost,
            (float) $item->total_variance_cost,
            $item->reason_of_adjustment,
        ])->values()->all();
    }

    private function receivingSheet(string $title, string $replenishmentType): UniformArraySheet
    {
        $dates = $this->dateColumns();
        $totals = DB::table('uniform_receiving_items as uri')
            ->join('uniform_receivings as ur', 'ur.id', '=', 'uri.receiving_id')
            ->join('uniform_replenishments as rpl', 'rpl.id', '=', 'ur.replenishment_id')
            ->where('ur.status', 'Posted')
            ->where('rpl.replenishment_type', $replenishmentType)
            ->whereDate('ur.posted_at', '>=', $this->variance->begin_date)
            ->whereDate('ur.posted_at', '<=', $this->variance->ending_date)
            ->select('uri.uniform_item_id', DB::raw('DATE(ur.posted_at) as movement_date'), DB::raw('SUM(uri.quantity) as quantity'))
            ->groupBy('uri.uniform_item_id', DB::raw('DATE(ur.posted_at)'))
            ->get()
            ->groupBy('uniform_item_id');

        return new UniformArraySheet($title, $this->movementHeadings($dates), $this->movementRows($dates, $totals, 'uniform_item_id'));
    }

    private function issuanceSheet(string $title, string $type): UniformArraySheet
    {
        $dates = $this->dateColumns();
        $query = DB::table('request_and_issuance_items as rii')
            ->join('request_and_issuances as rai', 'rai.request_number', '=', 'rii.requested_number_id')
            ->where('rai.status', 'Posted')
            ->whereDate('rai.date_posted', '>=', $this->variance->begin_date)
            ->whereDate('rai.date_posted', '<=', $this->variance->ending_date);

        if ($type === 'free') {
            $query->whereRaw('LOWER(rai.issuance_type) LIKE ?', ['%free%']);
        } else {
            $query->whereRaw('LOWER(rai.issuance_type) LIKE ?', ['%authority%']);
        }

        $totals = $query
            ->select('rii.uniform_item_no', DB::raw('DATE(rai.date_posted) as movement_date'), DB::raw('SUM(rii.requested_quantity) as quantity'))
            ->groupBy('rii.uniform_item_no', DB::raw('DATE(rai.date_posted)'))
            ->get()
            ->groupBy('uniform_item_no');

        return new UniformArraySheet($title, $this->movementHeadings($dates), $this->movementRows($dates, $totals, 'item_no'));
    }

    private function movementHeadings(array $dates): array
    {
        return ['Item Code', 'Item Description', ...$dates];
    }

    private function movementRows(array $dates, Collection $totals, string $keyField): array
    {
        return $this->variance->items->map(function ($item) use ($dates, $totals, $keyField) {
            $key = $keyField === 'uniform_item_id' ? $item->uniform_item_id : $item->item_no;
            $itemTotals = $totals->get($key, collect())->keyBy('movement_date');

            return [
                $item->item_no,
                $item->description,
                ...array_map(
                    fn ($date) => (float) optional($itemTotals->get($date))->quantity,
                    $dates
                ),
            ];
        })->values()->all();
    }

    private function dateColumns(): array
    {
        $dates = [];
        $date = Carbon::parse($this->variance->begin_date);
        $endingDate = Carbon::parse($this->variance->ending_date);

        while ($date->lte($endingDate)) {
            $dates[] = $date->format('Y-m-d');
            $date->addDay();
        }

        return $dates;
    }
}
