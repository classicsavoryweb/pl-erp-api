<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\AdjustmentTypeExport;
use App\Http\Controllers\Controller;
use App\Models\AdjustmentType;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AdjustmentTypeController extends Controller
{
    public function getAllAdjustmentTypes(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $adjustment_type = AdjustmentType::query();
            if ($request->has(["column_type", "key_word"])) {
                $adjustment_type = $adjustment_type->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $adjustment_type = $adjustment_type->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $adjustment_type = $adjustment_type->get();
                return Excel::download(new AdjustmentTypeExport($adjustment_type), "adjustment_type.xls");
            }

            $adjustment_type = $adjustment_type->paginate($perPage);
            return $this->sendResponse($adjustment_type);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAdjustmentTypes(Request $request) {
        $request["export"] = true;

        return $this->getAllAdjustmentTypes($request);
    }

    public function getAdjustmentTypes(Request $request) {
        try {
            $adjustment_type = AdjustmentType::where("status", "active")->get();
            return $this->sendResponse(["adjustment_types" => $adjustment_type]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createAdjustmentType(Request $request) {
        try {
            $request->validate([
                "description" => "required|unique:adjustment_types",
                "status" => "required",
            ]);

            AdjustmentType::create([
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Adjustment type created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editAdjustmentType(Request $request, $id) {
        try {
            $request->validate([
                "description" => "required|unique:adjustment_types,description,".$id,
                "status" => "required",
            ]);

            AdjustmentType::where("id", $id)->update([
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Adjustment type updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
