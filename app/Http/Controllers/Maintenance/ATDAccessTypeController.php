<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\ATDAccessTypeExport;
use App\Http\Controllers\Controller;
use App\Models\ATDAccessType;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class ATDAccessTypeController extends Controller
{
    public function getAllATDAccessTypes(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = ATDAccessType::query();

            if ($request->has(["column_type", "key_word"]) && $request->key_word !== null) {
                $query = $query->where($request->column_type, "LIKE", "%".$request->key_word."%");
            }

            if ($request->has("status") && $request->status !== null && $request->status !== "") {
                $query = $query->where("status", $request->status);
            }

            if ($request->has("export") && $request->export) {
                $records = $query->get();
                return Excel::download(new ATDAccessTypeExport($records), "atd-access-types.xls");
            }

            $data = $query->paginate($perPage);

            // Map role ids to names for display
            $roleIds = collect($data->items())
                ->pluck('roles')
                ->flatten()
                ->filter()
                ->unique()
                ->values();

            $roleMap = $roleIds->isNotEmpty()
                ? Role::whereIn('id', $roleIds)->pluck('name', 'id')
                : collect();

            $data->getCollection()->transform(function ($item) use ($roleMap) {
                $roleIds = collect((array) $item->roles)->map(fn($id) => (int) $id)->values()->all();
                $item->roles = $roleIds;
                $item->roles_display = collect($roleIds)
                    ->map(fn($id) => $roleMap[$id] ?? $id)
                    ->values()
                    ->all();
                return $item;
            });

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
            $request->validate([
                "category" => "required",
                "roles" => "required|array|min:1",
                "roles.*" => "exists:roles,id",
                "status" => "required",
            ]);

            ATDAccessType::create([
                "category" => $request->category,
                "roles" => collect($request->roles)->map(fn($id) => (int)$id)->values()->all(),
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "ATD Access Type created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editATDAccessType(Request $request, $id)
    {
        try {
            $request->validate([
                "category" => "required",
                "roles" => "required|array|min:1",
                "roles.*" => "exists:roles,id",
                "status" => "required",
            ]);

            ATDAccessType::where("id", $id)->update([
                "category" => $request->category,
                "roles" => collect($request->roles)->map(fn($id) => (int)$id)->values()->all(),
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "ATD Access Type updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
