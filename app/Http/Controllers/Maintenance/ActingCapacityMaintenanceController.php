<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\ActingCapacityReasonExport;
use App\Http\Controllers\Controller;
use App\Models\ActingCapacityReason;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class ActingCapacityMaintenanceController extends Controller
{
    public function getAllActingCapacityReasons(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $reasons = ActingCapacityReason::query();
            if ($request->has(["column_type", "key_word"])) {
               $reasons = $reasons->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $reasons = $reasons->where("status", $request->status);
            }
            
            if ($request->has(["export"]) && $request->export) {
                $reasons = $reasons->get();
                return Excel::download(new ActingCapacityReasonExport($reasons), "reasons.xls");
            }

            $reasons = $reasons->paginate($perPage);
            return $this->sendResponse($reasons);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportActingCapacityReasons(Request $request) {
        $request["export"] = true;

        return $this->getAllActingCapacityReason($request);
    }

    public function getActingCapacityReasons(Request $request) {
        try {
            $reasons = ActingCapacityReason::where("status", "active")->get();
            return $this->sendResponse(["reasons" => $reasons]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createActingCapacityReason(Request $request) {
        try {
            $request->validate([
                "reason" => "required|unique:acting_capacity_reason",
                "status" => "required",
            ]);

            ActingCapacityReason::create([
                "reason" => $request->reason,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Reasin created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editActingCapacityReason(Request $request, $id) {
        try {
            $request->validate([
                "reason" => "required|unique:acting_capacity_reason,reason,".$id,
                "status" => "required",
            ]);

            ActingCapacityReason::where("id", $id)->update([
                "reason" => $request->reason,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Reason updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
