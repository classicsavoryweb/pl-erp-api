<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashIssuance;
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
                $replenishments = $query->orderBy('created_at', 'desc')->get();
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

    private function exportReplenishments($replenishments)
    {
        $headers = [
            'Replenishment ID', 'Transaction Date', 'Branches',
            'Total Cash Issuance Amount', 'Total Over/Under Amount', 'Total Replenishment', 'Status', 'Date Created',
        ];

        $rows = $replenishments->map(function ($item) {
            return [
                $item->replenishment_id,
                $item->date,
                $item->branches_summary,
                $item->total_cash_issuance_amount,
                $item->total_overunder_amount,
                $item->total_replenishment_amount,
                $item->status,
                $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $export = new \App\Exports\PettyCash\ReplenishmentExport($rows->toBase(), $headers);
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

            // Unliquidated pa, wala pang alam kung magkano talaga nagastos, kaya
            // hindi pa dapat sumali sa Total Replenishment.
            $totalReplenishmentAmount = $issuances
                ->where('status', 'Liquidated')
                ->sum(fn($i) => (float) $i->amount + (float) ($i->overunder_amount ?? 0));

            return $this->sendResponse([
                'issuances'                    => $issuances,
                'total_cash_issuance_amount'   => $totalCashIssuanceAmount,
                'total_overunder_amount'       => $totalOverunderAmount,
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
            // (Replenished status update ay galing sa ibang module pag na-fund na)
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

            // walang saysay ang replenishment kung lahat pa Unliquidated, wala pang
            // aktwal na nagastos na pwedeng i-reimburse
            $hasLiquidated = $issuances->contains(fn($i) => $i->status === 'Liquidated');
            if (!$hasLiquidated) {
                DB::rollBack();
                return $this->sendError(['message' => 'Cannot create a replenishment: all included transactions are still Unliquidated. At least one Liquidated transaction is required.']);
            }

            $totalCashIssuanceAmount = $issuances->sum(fn($i) => (float) $i->amount);
            $totalOverunderAmount = $issuances->sum(fn($i) => (float) ($i->overunder_amount ?? 0));

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

            // ang pag-flip ng issuance status papuntang 'Fully Liquidated' (at CS Hub sync)
            // ay ngayon nasa PettyCashAuditController::postAuditReplenishment na, hindi dito -
            // dapat lang ito mangyari kapag na-audit na talaga, hindi pa habang Posted lang
            // ng custodian (puwede pang baguhin ng auditor ang liquidation sa Review step)
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
