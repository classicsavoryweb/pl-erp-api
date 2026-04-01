<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\RegionExport;
use App\Http\Controllers\Controller;
use App\Models\Region;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RegionController extends Controller
{
    public function getAllRegions(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $regions = Region::query()->orderBy('name', 'ASC');

            if ($request->has(["column_type", "key_word"])) {
                $regions = $regions->where($request->column_type, "LIKE", "%" . $request->key_word . "%");
            }

            if ($request->has(["status"])) {
                $regions = $regions->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                return Excel::download(new RegionExport($regions->get()), "regions.xls");
            }

            return $this->sendResponse($regions->paginate($perPage));
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllRegions(Request $request)
    {
        $request["export"] = true;

        return $this->getAllRegions($request);
    }

    public function getRegions()
    {
        try {
            $regions = Region::where("status", "active")
                ->orderBy("name", "ASC")
                ->get();

            return $this->sendResponse(["regions" => $regions]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createRegion(Request $request)
    {
        try {
            $request->validate([
                "name" => "required|unique:regions,name",
                "description" => "required",
                "status" => "required",
            ]);

            Region::create([
                "name" => trim((string) $request->name),
                "description" => trim((string) $request->description),
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Region created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editRegion(Request $request, $id)
    {
        try {
            $request->validate([
                "name" => "required|unique:regions,name," . $id,
                "description" => "required",
                "status" => "required",
            ]);

            Region::where("id", $id)->update([
                "name" => trim((string) $request->name),
                "description" => trim((string) $request->description),
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Region updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
