<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\VaccinneBrandExport;
use App\Http\Controllers\Controller;
use App\Models\Vaccine;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class VaccineController extends Controller
{
    public function getAllVaccines(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $vaccines = Vaccine::query();

            if ($request->has(["column_type", "key_word"])) {
               $vaccines = $vaccines->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $vaccines = $vaccines->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $vaccines = $vaccines->get();
                return Excel::download(new VaccinneBrandExport($vaccines), "vaccines.xls");
            }

            $vaccines= $vaccines->paginate($perPage);

            return $this->sendResponse($vaccines);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllVaccines(Request $request) {
        $request["export"] = true;

        return $this->getAllVaccines($request);
    }

    public function getVaccines(Request $request) {
        try {

            $vaccines = Vaccine::where("status", "active")->get();
            return $this->sendResponse(["vaccines" => $vaccines]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createVaccine(Request $request) {
        try {
            $request->validate([
                "description" => "required|unique:vaccines",
                "status" => "required"
            ]);
            Vaccine::create([
                "description" => $request->description,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Vaccine created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editVaccine (Request $request, $id) {
        try {
            $request->validate([
                "description" => "required|unique:vaccines,description,".$id,
                "status" => "required"
            ]);
            $vaccine = Vaccine::find($id);
            $vaccine->update([
                "description" => $request->description,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Vaccine updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
