<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\CoeReason;
use Illuminate\Http\Request;
use Exception;
class CoeReasonController extends Controller
{
    public function getAllCoeReasons(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $reasons = CoeReason::query();

            if ($request->has(["column_type", "key_word"])) {
               $reasons = $reasons->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $reasons = $reasons->where("status", $request->status);
            }

            $reasons = $reasons->paginate($perPage);
            return $this->sendResponse($reasons);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getCoeReasons(Request $request) {
        try {
            $reasons = CoeReason::where("status", "active")->get();

            return $this->sendResponse([ "coe_reasons" => $reasons]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createCoeReason(Request $request) {
        try {
            $request->validate([
                "reason" => "required|unique:coe_reasons",
                "status" => "required"
            ]);

            CoeReason::create([
                "reason" => $request->reason,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "COE reason created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editCoeReason(Request $request, $id) {
        try {
            $request->validate([
                "reason" => "required|unique:coe_reasons,reason,".$id,
                "status" => "required"
            ]);

            CoeReason::where("id", $id)->update([
                "reason" => $request->reason,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "COE reason updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
