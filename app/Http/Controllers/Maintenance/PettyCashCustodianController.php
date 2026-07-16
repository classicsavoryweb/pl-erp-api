<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\PettyCashCustodianExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PettyCashCustodian;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class PettyCashCustodianController extends Controller
{
    public function getAllCustodians(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $custodians = PettyCashCustodian::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if ($column === 'user.full_name') {
                    $custodians = $custodians->whereHas('user', function ($q) use ($keyword) {
                        $q->whereAny(['first_name', 'last_name', 'middle_name'], 'LIKE', "%$keyword%")
                            ->orWhere(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                    });
                } elseif (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);
                    $custodians = $custodians->whereHas($relation, function ($q) use ($relatedColumn, $keyword) {
                        $q->where($relatedColumn, 'LIKE', "%$keyword%");
                    });
                } else {
                    $custodians = $custodians->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $custodians = $custodians->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $custodians = $custodians->with(['user', 'branches', 'createdBy', 'updatedBy'])->get();
                return Excel::download(new PettyCashCustodianExport($custodians), "petty_cash_custodians.xls");
            }

            $custodians = $custodians->with(['user', 'branches', 'createdBy', 'updatedBy'])->orderBy('created_at', 'desc')->paginate($perPage);
            return $this->sendResponse($custodians);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllCustodians(Request $request)
    {
        $request["export"] = true;
        return $this->getAllCustodians($request);
    }

    public function createCustodian(Request $request)
    {
        try {
            $request->validate([
                "user_id"         => ["required", "exists:users,id", Rule::unique("petty_cash_custodians", "user_id")->where(fn($q) => $q->where("status", "active"))],
                "branch_ids"      => "required|array|min:1",
                "branch_ids.*"    => "required|exists:branches,id",
                "total_cash_fund" => "required|numeric|min:0",
                "status"          => "required|in:active,inactive",
            ]);

            $conflict = $this->checkBranchConflicts($request->branch_ids);
            if ($conflict) {
                return $this->sendError(["message" => $conflict]);
            }

            DB::transaction(function () use ($request) {
                $custodian = PettyCashCustodian::create([
                    "user_id"              => $request->user_id,
                    "total_cash_fund"      => $request->total_cash_fund,
                    "remaining_cash_fund"  => $request->total_cash_fund,
                    "status"               => $request->status,
                    "created_by_id"        => auth()->id(),
                ]);

                $custodian->branches()->sync($request->branch_ids);
            });

            return $this->sendResponse(["message" => "Custodian created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editCustodian(Request $request, $id)
    {
        try {
            $custodian = PettyCashCustodian::findOrFail($id);
            if ($custodian->status === 'inactive') {
                return $this->sendError(["message" => "Inactive custodians cannot be edited. Please create a new custodian."]);
            }

            $request->validate([
                "user_id"         => ["required", "exists:users,id", Rule::unique("petty_cash_custodians", "user_id")->where(fn($q) => $q->where("status", "active"))->ignore($id)],
                "branch_ids"      => "required|array|min:1",
                "branch_ids.*"    => "required|exists:branches,id",
                "total_cash_fund" => "required|numeric|min:0",
                "status"          => "required|in:active,inactive",
            ]);

            $conflict = $this->checkBranchConflicts($request->branch_ids, (int) $id);
            if ($conflict) {
                return $this->sendError(["message" => $conflict]);
            }

            DB::transaction(function () use ($request, $id) {
                $custodian = PettyCashCustodian::findOrFail($id);
                $custodian->update([
                    "user_id"         => $request->user_id,
                    "total_cash_fund" => $request->total_cash_fund,
                    "status"          => $request->status,
                    "updated_by_id"   => auth()->id(),
                ]);

                $custodian->branches()->sync($request->branch_ids);
            });

            return $this->sendResponse(["message" => "Custodian updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    private function checkBranchConflicts(array $branchIds, ?int $excludeId = null): ?string
    {
        $query = DB::table('petty_cash_custodian_branch')
            ->join('petty_cash_custodians', 'petty_cash_custodian_branch.custodian_id', '=', 'petty_cash_custodians.id')
            ->where('petty_cash_custodians.status', 'active')
            ->whereIn('petty_cash_custodian_branch.branch_id', $branchIds);

        if ($excludeId) {
            $query->where('petty_cash_custodians.id', '!=', $excludeId);
        }

        $conflictingBranchIds = $query->pluck('petty_cash_custodian_branch.branch_id')->toArray();

        if (!empty($conflictingBranchIds)) {
            $branchNames = Branch::whereIn('id', $conflictingBranchIds)->pluck('name')->implode(', ');
            return "The following branches are already assigned to an active custodian: {$branchNames}. Please deactivate the current custodian before reassigning.";
        }

        return null;
    }
}
