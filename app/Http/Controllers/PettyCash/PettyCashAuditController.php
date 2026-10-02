<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PettyCashReplenishment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class PettyCashAuditController extends Controller
{
    public function getAllReplenishmentsForAudit(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            // "Pending" dito ay tumutukoy sa mga Posted na ni custodian pero pending pa i-audit.
            $statusMap = [
                'Pending'  => 'Posted',
                'Reviewed' => 'Reviewed',
                'Audited'  => 'Audited',
                'Replenished'  => 'Replenished',
            ];

            $query = PettyCashReplenishment::with(['custodian.user']);

            if ($request->filled('status')) {
                $query->where('status', $statusMap[$request->status] ?? 'Posted');
            } else {
                // walang piniling status filter, ipakita lahat ng nasa audit pipeline
                $query->whereIn('status', ['Posted', 'Reviewed', 'Audited', 'Replenished']);
            }

            if ($request->filled('custodian_id')) {
                $query->where('petty_cash_custodian_id', $request->custodian_id);
            }

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
                $branch = Branch::find($request->branch_id);
                if ($branch) {
                    $query->where('branches_summary', 'like', '%' . $branch->name . '%');
                }
            }

            $replenishments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->sendResponse($replenishments);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function reviewReplenishment($id)
    {
        try {
            DB::beginTransaction();

            $replenishment = PettyCashReplenishment::lockForUpdate()->findOrFail($id);

            if ($replenishment->status !== 'Posted') {
                DB::rollBack();
                return $this->sendError(['message' => 'Only Posted replenishments can be marked as Reviewed.']);
            }

            $replenishment->update([
                'status'         => 'Reviewed',
                'reviewed_by_id' => auth()->id(),
                'reviewed_at'    => now(),
            ]);

            DB::commit();
            return $this->sendResponse(['message' => 'Replenishment marked as Reviewed.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function postAuditReplenishment($id)
    {
        try {
            DB::beginTransaction();

            $replenishment = PettyCashReplenishment::lockForUpdate()->findOrFail($id);

            if ($replenishment->status !== 'Reviewed') {
                DB::rollBack();
                return $this->sendError(['message' => 'Only Reviewed replenishments can be marked as Audited.']);
            }

            $replenishment->update([
                'status'        => 'Audited',
                'audited_by_id' => auth()->id(),
                'audited_at'    => now(),
            ]);

            DB::commit();
            return $this->sendResponse(['message' => 'Replenishment marked as Audited.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
