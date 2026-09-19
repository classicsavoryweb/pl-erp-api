<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PettyCashReplenishment;
use Illuminate\Http\Request;
use Exception;

class PettyCashAuditController extends Controller
{
    public function getAllReplenishmentsForAudit(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            // "Pending" dito ay tumutukoy sa mga Posted na ni custodian pero pending pa i-audit.
            // Reviewed/Audited ay hindi pa aktwal na na-a-apply kahit saan (walang pang process/action
            // para dito), kaya wala munang ibabalik na laman ang mga filter na yan hangga't wala pang
            // ganitong mekanismo.
            $statusMap = [
                'Pending'  => 'Posted',
                'Reviewed' => 'Reviewed',
                'Audited'  => 'Audited',
            ];
            $status = $request->filled('status') ? ($statusMap[$request->status] ?? 'Posted') : 'Posted';

            $query = PettyCashReplenishment::where('status', $status)
                ->with(['custodian.user']);

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
}
