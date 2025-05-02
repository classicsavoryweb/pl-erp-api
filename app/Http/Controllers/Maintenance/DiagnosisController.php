<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Exception;
class DiagnosisController extends Controller
{
    public function getAllDiagnosis(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $diagnosis = Diagnosis::paginate($perPage);
            return $this->sendResponse($diagnosis);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getDiagnosis(Request $request) {
        try {
            $diagnosis = Diagnosis::where("status", "active")->get();
            return $this->sendResponse(["diagnosis" => $diagnosis]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createDiagnosis(Request $request) {
        try {
            $request->validate([
                "description" => "required",
                "status" => "required",
            ]);

            Diagnosis::create([
                "description" => $request->description,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Diagnosis created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editDiagnosis(Request $request, $id) {
        try {
            $request->validate([
                "description" => "required",
                "status" => "required",
            ]);

            Diagnosis::where("id", $id)->update([
                "description" => $request->description,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Diagnosis updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
