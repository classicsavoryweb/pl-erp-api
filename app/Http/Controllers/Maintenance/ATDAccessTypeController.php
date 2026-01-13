<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\ATDAccessTypeExport;
use App\Http\Controllers\Controller;
use App\Models\ATDAccessType;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class ATDAccessTypeController extends Controller
{
    public function getAllATDAccessTypes(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = ATDAccessType::query();

            if ($request->has(["column_type", "key_word"]) && $request->key_word !== null) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (str_contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);
                    $query = $query->whereHas($relation, function ($q) use ($relatedColumn, $keyword) {
                        $q->where($relatedColumn, 'LIKE', "%".$keyword."%");
                    });
                } else {
                    $query = $query->where($column, "LIKE", "%".$keyword."%");
                }
            }

            if ($request->has("status") && $request->status !== null && $request->status !== "") {
                $query = $query->where("status", $request->status);
            }

            if ($request->has("export") && $request->export) {
                $records = $query->with(["deduction", "role"])->get();
                return Excel::download(new ATDAccessTypeExport($records), "atd-access-types.xls");
            }

            $data = $query->with(["deduction", "role"])->paginate($perPage);

            return $this->sendResponse($data);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllATDAccessTypes(Request $request)
    {
        $request["export"] = true;
        return $this->getAllATDAccessTypes($request);
    }

    public function createATDAccessType(Request $request)
    {
        try {
            $rules = [
                "roles" => "required|array|min:1",
                "roles.*" => "exists:roles,id",
                "status" => "required",
            ];

            if ($request->has("deduction_ids")) {
                $rules["deduction_ids"] = "required|array|min:1";
                $rules["deduction_ids.*"] = "exists:deductions,id";
            } else {
                $rules["deduction_id"] = "required|exists:deductions,id";
            }

            $request->validate($rules);

            $deductionIds = $request->has("deduction_ids")
                ? collect($request->deduction_ids)->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [(int) $request->deduction_id];

            $roleIds = collect($request->roles)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            foreach ($roleIds as $roleId) {
                foreach ($deductionIds as $deductionId) {
                    ATDAccessType::create([
                        "deduction_id" => $deductionId,
                        "role_id" => $roleId,
                        "status" => $request->status,
                    ]);
                }
            }

            return $this->sendResponse(["message" => "ATD Access Type created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editATDAccessType(Request $request, $id)
    {
        try {
            $rules = [
                "roles" => "required|array|min:1",
                "roles.*" => "exists:roles,id",
                "status" => "required",
            ];

            if ($request->has("deduction_ids")) {
                $rules["deduction_ids"] = "required|array|min:1";
                $rules["deduction_ids.*"] = "exists:deductions,id";
            } else {
                $rules["deduction_id"] = "required|exists:deductions,id";
            }

            $request->validate($rules);

            $deductionIds = $request->has("deduction_ids")
                ? collect($request->deduction_ids)->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [(int) $request->deduction_id];

            $roleIds = collect($request->roles)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            foreach ($roleIds as $roleId) {
                ATDAccessType::where("role_id", $roleId)->delete();
                foreach ($deductionIds as $deductionId) {
                    ATDAccessType::create([
                        "deduction_id" => $deductionId,
                        "role_id" => $roleId,
                        "status" => $request->status,
                    ]);
                }
            }

            return $this->sendResponse(["message" => "ATD Access Type updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
