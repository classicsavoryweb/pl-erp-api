<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\PettyCashApproverExport;
use App\Http\Controllers\Controller;
use App\Models\PettyCashApprover;
use Illuminate\Http\Request;
use Exception;
use Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class PettyCashApproverController extends Controller
{
    public function getAllApprovers(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $approvers = PettyCashApprover::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);
                    $approvers = $approvers->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                        $query->where($relatedColumn, 'LIKE', "%$keyword%");
                    });
                } else {
                    $approvers = $approvers->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $approvers = $approvers->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $approvers = $approvers->with(["employees", "branches"])->get();
                return Excel::download(new PettyCashApproverExport($approvers), "petty_cash_approvers.xls");
            }

            $approvers = $approvers->with(["employees.current_position", "branches"])->paginate($perPage);
            return $this->sendResponse($approvers);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllApprovers(Request $request)
    {
        $request["export"] = true;
        return $this->getAllApprovers($request);
    }

    public function createApprover(Request $request)
    {
        try {
            $request->validate([
                "description" => "required|unique:petty_cash_approvers",
                "use_same_range" => "required|boolean",
                "min_amount" => "required_if:use_same_range,true|nullable|numeric",
                "max_amount" => "required_if:use_same_range,true|nullable|numeric",
                "status" => "required",
                "branch_ids" => "required|array|min:1",
                "branch_ids.*" => "required|exists:branches,id",
                "employees" => "required|array|min:1",
                "employees.*.employee_id" => "required|exists:employees,id",
                "employees.*.min_amount" => "required_if:use_same_range,false|nullable|numeric",
                "employees.*.max_amount" => "required_if:use_same_range,false|nullable|numeric",
            ]);

            $employeeIds = collect($request->employees)->pluck('employee_id')->toArray();
            $conflicts = $this->getEmployeeBranchConflicts($employeeIds, $request->branch_ids);
            if (count($conflicts)) {
                return $this->sendError(['message' => $this->buildConflictMessage($conflicts)]);
            }

            DB::transaction(function () use ($request) {
                $approver = PettyCashApprover::create([
                    "description" => $request->description,
                    "use_same_range" => $request->use_same_range,
                    "min_amount" => $request->use_same_range ? $request->min_amount : null,
                    "max_amount" => $request->use_same_range ? $request->max_amount : null,
                    "status" => $request->status,
                ]);

                $approver->branches()->sync($request->branch_ids);

                $syncData = [];
                foreach ($request->employees as $emp) {
                    $syncData[$emp['employee_id']] = [
                        'min_amount' => $request->use_same_range ? $request->min_amount : $emp['min_amount'],
                        'max_amount' => $request->use_same_range ? $request->max_amount : $emp['max_amount'],
                    ];
                }
                $approver->employees()->sync($syncData);
            });

            return $this->sendResponse(["message" => "Approver group created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editApprover(Request $request, $id)
    {
        try {
            $request->validate([
                "description" => "required|unique:petty_cash_approvers,description," . $id,
                "use_same_range" => "required|boolean",
                "min_amount" => "required_if:use_same_range,true|nullable|numeric",
                "max_amount" => "required_if:use_same_range,true|nullable|numeric",
                "status" => "required",
                "branch_ids" => "required|array|min:1",
                "branch_ids.*" => "required|exists:branches,id",
                "employees" => "required|array|min:1",
                "employees.*.employee_id" => "required|exists:employees,id",
                "employees.*.min_amount" => "required_if:use_same_range,false|nullable|numeric",
                "employees.*.max_amount" => "required_if:use_same_range,false|nullable|numeric",
            ]);

            $employeeIds = collect($request->employees)->pluck('employee_id')->toArray();
            $conflicts = $this->getEmployeeBranchConflicts($employeeIds, $request->branch_ids, (int) $id);
            if (count($conflicts)) {
                return $this->sendError(['message' => $this->buildConflictMessage($conflicts)]);
            }

            DB::transaction(function () use ($request, $id) {
                $approver = PettyCashApprover::findOrFail($id);
                $approver->update([
                    "description" => $request->description,
                    "use_same_range" => $request->use_same_range,
                    "min_amount" => $request->use_same_range ? $request->min_amount : null,
                    "max_amount" => $request->use_same_range ? $request->max_amount : null,
                    "status" => $request->status,
                ]);

                $approver->branches()->sync($request->branch_ids);

                $syncData = [];
                foreach ($request->employees as $emp) {
                    $syncData[$emp['employee_id']] = [
                        'min_amount' => $request->use_same_range ? $request->min_amount : $emp['min_amount'],
                        'max_amount' => $request->use_same_range ? $request->max_amount : $emp['max_amount'],
                    ];
                }
                $approver->employees()->sync($syncData);
            });

            return $this->sendResponse(["message" => "Approver group updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    // check kung may existing approver na sa parehong branch
    private function getEmployeeBranchConflicts(array $employeeIds, array $branchIds, ?int $excludeId = null): array
    {
        $query = DB::table('petty_cash_approver_employee as pae')
            ->join('petty_cash_approver_branch as pab', 'pae.approver_id', '=', 'pab.approver_id')
            ->join('petty_cash_approvers as pa', 'pa.id', '=', 'pae.approver_id')
            ->join('employees as e', 'e.id', '=', 'pae.employee_id')
            ->join('branches as b', 'b.id', '=', 'pab.branch_id')
            ->where('pa.status', 'Active')
            ->whereIn('pae.employee_id', $employeeIds)
            ->whereIn('pab.branch_id', $branchIds)
            ->select(
                DB::raw("CONCAT(e.last_name, ', ', e.first_name) as employee_name"),
                'b.name as branch_name',
                'pa.description as approver_group'
            )
            ->distinct();

        if ($excludeId !== null) {
            $query->where('pa.id', '!=', $excludeId);
        }

        return $query->get()->toArray();
    }

    private function buildConflictMessage(array $conflicts): string
    {
        $lines = array_map(
            fn($c) => "• {$c->employee_name} → {$c->branch_name} (Group: {$c->approver_group})",
            $conflicts
        );
        return "The following employees are already assigned as approvers for the selected branch(es) in an active group:\n" . implode("\n", $lines);
    }
}
