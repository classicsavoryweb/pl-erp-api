<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashIssuance;
use App\Models\PettyCashLiquidation;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashReplenishmentSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ReplenishmentController extends Controller
{
    private function getActiveCustodian()
    {
        return PettyCashCustodian::where('user_id', auth()->id())
            ->where('status', 'active')
            ->with(['branches'])
            ->first();
    }

    public function getAllReplenishments(Request $request)
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $perPage = $request->per_page ?? 10;

            $query = PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id);

            if ($request->filled('replenishment_id')) {
                $query->where('replenishment_id', 'like', '%' . $request->replenishment_id . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            if ($request->filled('branch_id')) {
                $branch = \App\Models\Branch::find($request->branch_id);
                if ($branch) {
                    $query->where('branches_summary', 'like', '%' . $branch->name . '%');
                }
            }

            if ($request->has(['export']) && $request->export) {
                $replenishments = $query->with('snapshots')->orderBy('replenishment_id', 'desc')->get();
                return $this->exportReplenishments($replenishments);
            }

            $replenishments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->sendResponse($replenishments);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function exportAllReplenishments(Request $request)
    {
        $request['export'] = true;
        return $this->getAllReplenishments($request);
    }

    // detailed export - isang row per liquidation (kapareho ng audit export), hindi per replenishment
    private function exportReplenishments($replenishments)
    {
        $headers = [
            'Replenishment ID', 'Branches', 'Replenishment Status', 'PCV Number', 'Liquidation ID', 'Transaction Date',
            'Requested By', 'Received By', 'Approved By', 'Cash Issuance Amount', 'Over/Under Amount',
            'Source', 'Liquidation Date', 'Ref Doc No.', 'Description', 'Liquidated Amount',
            'Expense Type', 'Snapshot Status', 'Current Status',
        ];

        $employeeName = fn($emp) => $emp ? $emp->last_name . ', ' . $emp->first_name : '—';

        // liquidated muna lahat sa taas, tapos unliquidated sa baba - hindi
        // pinagsasabay per replenishment dahil nakakalito pag paulit-ulit lumabas
        // ang parehong CI (Unliquidated noong isang replenishment, Liquidated na sa sunod)
        $liquidatedRows = collect();
        $unliquidatedRows = collect();

        foreach ($replenishments as $replenishment) {
            // kapareho ng scope rule sa audit view: Liquidated-at-snapshot lang ang may liquidation detail
            $liquidatedSnapshots = $replenishment->snapshots
                ->where('status_at_snapshot', 'Liquidated')
                ->keyBy('petty_cash_issuance_id');

            $liquidations = PettyCashLiquidation::with(['expenseType', 'issuance.requestedBy', 'issuance.receivedBy', 'issuance.approvedBy'])
                ->whereIn('petty_cash_issuance_id', $liquidatedSnapshots->keys())
                ->orderBy('liquidation_id', 'asc')
                ->get();

            foreach ($liquidations as $liquidation) {
                $issuance = $liquidation->issuance;
                $snapshot = $liquidatedSnapshots->get($liquidation->petty_cash_issuance_id);

                $liquidatedRows->push([
                    $replenishment->replenishment_id,
                    $replenishment->branches_summary,
                    $replenishment->status,
                    $snapshot->pcv_number ?? '—',
                    $liquidation->liquidation_id,
                    $issuance->date ?? '—',
                    $employeeName($issuance->requestedBy),
                    $employeeName($issuance->receivedBy),
                    $employeeName($issuance->approvedBy),
                    $issuance->amount,
                    $issuance->overunder_amount,
                    $issuance->source ?? '—',
                    $liquidation->date,
                    $liquidation->reference_document_number,
                    $liquidation->description,
                    $liquidation->amount,
                    $liquidation->expenseType->description ?? '—',
                    $snapshot->status_at_snapshot,
                    $issuance->status ?? '—',
                ]);
            }

            // display-only reference, gaya ng sa audit view - mga Unliquidated pa noong snapshot
            $unliquidatedSnapshots = $replenishment->snapshots->where('status_at_snapshot', 'Unliquidated');

            foreach ($unliquidatedSnapshots as $snapshot) {
                $issuance = PettyCashIssuance::with(['requestedBy', 'receivedBy', 'approvedBy'])
                    ->find($snapshot->petty_cash_issuance_id);

                if (!$issuance) {
                    continue;
                }

                $unliquidatedRows->push([
                    $replenishment->replenishment_id,
                    $replenishment->branches_summary,
                    $replenishment->status,
                    $snapshot->pcv_number ?? '—',
                    '—',
                    $issuance->date ?? '—',
                    $employeeName($issuance->requestedBy),
                    $employeeName($issuance->receivedBy),
                    $employeeName($issuance->approvedBy),
                    $issuance->amount,
                    '—',
                    $issuance->source ?? '—',
                    '—',
                    '—',
                    '—',
                    '—',
                    '—',
                    $snapshot->status_at_snapshot,
                    $issuance->status ?? $snapshot->status_at_snapshot,
                ]);
            }
        }

        $rows = $liquidatedRows->concat($unliquidatedRows);

        $export = new \App\Exports\PettyCash\ReplenishmentExport($rows, $headers);
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'petty-cash-replenishments.xls');
    }

    public function getPendingCashIssuances()
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $branchIds = $custodian->branches->pluck('id');

            $issuances = PettyCashIssuance::with(['branch', 'requestedBy'])
                ->whereIn('branch_id', $branchIds)
                ->whereIn('status', ['Unliquidated', 'Liquidated'])
                ->orderBy('date')
                ->get();

            $totalCashIssuanceAmount = $issuances->sum(fn($i) => (float) $i->amount);
            $totalOverunderAmount = $issuances->sum(fn($i) => (float) ($i->overunder_amount ?? 0));
            $totalUnliquidatedAmount = $issuances
                ->where('status', 'Unliquidated')
                ->sum(fn($i) => (float) $i->amount);

            // Liquidated lang, hindi pa kasali ang Unliquidated sa total replenishment
            $totalReplenishmentAmount = $issuances
                ->where('status', 'Liquidated')
                ->sum(fn($i) => (float) $i->amount + (float) ($i->overunder_amount ?? 0));

            return $this->sendResponse([
                'issuances'                    => $issuances,
                'total_cash_issuance_amount'   => $totalCashIssuanceAmount,
                'total_overunder_amount'       => $totalOverunderAmount,
                'total_unliquidated_amount'    => $totalUnliquidatedAmount,
                'total_replenishment_amount'   => $totalReplenishmentAmount,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function hasUnsettledReplenishment($custodian)
    {
        return PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id)
            ->whereNotIn('status', ['Voided', 'Replenished'])
            ->exists();
    }

    // tinatawag ito bago pa man buksan yung Add Replenishment modal sa frontend
    public function checkExistingReplenishment()
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            return $this->sendResponse([
                'has_existing_replenishment' => $this->hasUnsettledReplenishment($custodian),
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getReplenishment($id)
    {
        try {
            $replenishment = PettyCashReplenishment::with(['snapshots' => function ($q) {
                $q->orderBy('transaction_date');
            }])->findOrFail($id);

            return $this->sendResponse($replenishment);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function createReplenishment()
    {
        try {
            DB::beginTransaction();

            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                DB::rollBack();
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            // bawal mag-create ng bago kung may hindi pa Voided/Replenished na existing
            if ($this->hasUnsettledReplenishment($custodian)) {
                DB::rollBack();
                return $this->sendError(['message' => 'You have an existing replenishment that is not yet Replenished']);
            }

            $branchIds = $custodian->branches->pluck('id');

            // lock muna, baka may kasabay na liquidation
            $issuances = PettyCashIssuance::with(['branch', 'requestedBy'])
                ->whereIn('branch_id', $branchIds)
                ->whereIn('status', ['Unliquidated', 'Liquidated'])
                ->lockForUpdate()
                ->get();

            if ($issuances->isEmpty()) {
                DB::rollBack();
                return $this->sendError(['message' => 'No unliquidated or liquidated transactions found to replenish.']);
            }

            // kailangan ng at least isang Liquidated, walang saysay kung lahat pa Unliquidated
            $hasLiquidated = $issuances->contains(fn($i) => $i->status === 'Liquidated');
            if (!$hasLiquidated) {
                DB::rollBack();
                return $this->sendError(['message' => 'Cannot create a replenishment: all included transactions are still Unliquidated. At least one Liquidated transaction is required.']);
            }

            $totalCashIssuanceAmount = $issuances->sum(fn($i) => (float) $i->amount);
            $totalOverunderAmount = $issuances->sum(fn($i) => (float) ($i->overunder_amount ?? 0));
            $totalUnliquidatedAmount = $issuances
                ->where('status', 'Unliquidated')
                ->sum(fn($i) => (float) $i->amount);

            // Liquidated lang isasama, wag yung Unliquidated pa
            $totalReplenishmentAmount = $issuances
                ->where('status', 'Liquidated')
                ->sum(fn($i) => (float) $i->amount + (float) ($i->overunder_amount ?? 0));

            $count = PettyCashReplenishment::count();
            $replenishmentId = 'REP' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);

            $replenishment = PettyCashReplenishment::create([
                'replenishment_id'            => $replenishmentId,
                'date'                        => now()->toDateString(),
                'petty_cash_custodian_id'     => $custodian->id,
                'branches_summary'            => $custodian->branches->pluck('name')->implode(', '),
                'total_cash_issuance_amount'  => $totalCashIssuanceAmount,
                'total_overunder_amount'      => $totalOverunderAmount,
                'total_unliquidated_amount'   => $totalUnliquidatedAmount,
                'total_replenishment_amount'  => $totalReplenishmentAmount,
                'status'                      => 'Pending',
                'created_by_id'               => auth()->id(),
            ]);

            foreach ($issuances as $issuance) {
                PettyCashReplenishmentSnapshot::create([
                    'petty_cash_replenishment_id' => $replenishment->id,
                    'petty_cash_issuance_id'      => $issuance->id,
                    'pcv_number'                  => $issuance->pcv_number,
                    'cshub_trans_no'              => $issuance->cshub_trans_no,
                    'branch_id'                   => $issuance->branch_id,
                    'branch_name'                 => $issuance->branch->name ?? '',
                    'transaction_date'            => $issuance->date,
                    'requested_by_name'           => $issuance->requestedBy
                        ? "{$issuance->requestedBy->last_name}, {$issuance->requestedBy->first_name}"
                        : '',
                    'amount'                      => $issuance->amount,
                    'unliquidated_amount'         => $issuance->unliquidated_amount,
                    'overunder_amount'            => $issuance->overunder_amount,
                    'status_at_snapshot'          => $issuance->status,
                ]);
            }

            DB::commit();
            return $this->sendResponse(['message' => 'Replenishment created successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function postReplenishment($id)
    {
        try {
            DB::beginTransaction();

            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                DB::rollBack();
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $replenishment = PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($replenishment->status !== 'Pending') {
                DB::rollBack();
                return $this->sendError(['message' => 'Only pending replenishments can be posted.']);
            }

            // Fully Liquidated flip (at CS Hub sync) ay nasa PettyCashAuditController na ngayon
            $replenishment->update(['status' => 'Posted']);

            DB::commit();

            return $this->sendResponse(['message' => 'Replenishment posted successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function voidReplenishment(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                DB::rollBack();
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $replenishment = PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($replenishment->status !== 'Pending') {
                DB::rollBack();
                return $this->sendError(['message' => 'Only pending replenishments can be voided.']);
            }

            $replenishment->update([
                'status'      => 'Voided',
                'void_reason' => $request->reason,
            ]);

            DB::commit();
            return $this->sendResponse(['message' => 'Replenishment voided successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
