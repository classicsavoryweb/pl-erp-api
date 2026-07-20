<?php

namespace App\Http\Services;

use App\Models\PhysicalCountCompare;
use App\Models\UniformItem;
use Illuminate\Support\Facades\DB;

class UniformMovementService
{
    /**
     * Independent aggregate of posted receiving/issuance movement for a date range,
     * bucketed by item and type. Used by both Physical Count and Variance generation
     * so both recompute the exact same numbers rather than one copying the other's
     * possibly-stale snapshot.
     */
    public function movementTotals(string $beginDate, string $endingDate): array
    {
        $receivings = DB::table('uniform_receiving_items as uri')
            ->join('uniform_receivings as ur', 'ur.id', '=', 'uri.receiving_id')
            ->join('uniform_replenishments as rpl', 'rpl.id', '=', 'ur.replenishment_id')
            ->where('ur.status', 'Posted')
            ->whereDate('ur.posted_at', '>', $beginDate)
            ->whereDate('ur.posted_at', '<=', $endingDate)
            ->select('uri.uniform_item_id', 'rpl.replenishment_type', DB::raw('SUM(uri.quantity) as quantity'))
            ->groupBy('uri.uniform_item_id', 'rpl.replenishment_type')
            ->get();

        $issuances = DB::table('request_and_issuance_items as rii')
            ->join('request_and_issuances as rai', 'rai.request_number', '=', 'rii.requested_number_id')
            ->where('rai.status', 'Posted')
            ->whereDate('rai.date_posted', '>', $beginDate)
            ->whereDate('rai.date_posted', '<=', $endingDate)
            ->select('rii.uniform_item_no', 'rai.issuance_type', DB::raw('SUM(rii.requested_quantity) as quantity'))
            ->groupBy('rii.uniform_item_no', 'rai.issuance_type')
            ->get();

        $totals = [
            'purchasing' => [],
            'made_to_order' => [],
            'atd' => [],
            'free_uniform' => [],
        ];

        foreach ($receivings as $row) {
            $type = strtolower((string) $row->replenishment_type);
            $bucket = str_contains($type, 'made') ? 'made_to_order' : 'purchasing';
            $totals[$bucket][$row->uniform_item_id] = (float) $row->quantity;
        }

        foreach ($issuances as $row) {
            $type = strtolower((string) $row->issuance_type);
            $bucket = str_contains($type, 'free') ? 'free_uniform' : 'atd';
            $totals[$bucket][$row->uniform_item_no] = (float) $row->quantity;
        }

        return $totals;
    }

    public function beginningInventory(UniformItem $item, string $beginDate): float
    {
        $compare = PhysicalCountCompare::where('uniform_item_id', $item->id)
            ->whereDate('beginning_date', $beginDate)
            ->first();

        return $compare ? (float) $compare->stock_on_hand : 0.0;
    }

    /**
     * Batched equivalent of beginningInventory() for many items at once — avoids an
     * N+1 query per item, which is especially costly against a remote database when
     * computing lines for every active item (e.g. Variance generation).
     *
     * @param  \Illuminate\Support\Collection<int, UniformItem>  $items
     * @return array<int, float> keyed by uniform_item_id
     */
    public function beginningInventoryBatch($items, string $beginDate): array
    {
        $itemIds = $items->pluck('id')->all();

        $rows = PhysicalCountCompare::whereIn('uniform_item_id', $itemIds)
            ->whereDate('beginning_date', $beginDate)
            ->get(['uniform_item_id', 'stock_on_hand']);

        $byItem = [];
        foreach ($rows as $row) {
            $byItem[$row->uniform_item_id] = (float) $row->stock_on_hand;
        }

        $result = [];
        foreach ($items as $item) {
            $result[$item->id] = $byItem[$item->id] ?? 0.0;
        }

        return $result;
    }
}
