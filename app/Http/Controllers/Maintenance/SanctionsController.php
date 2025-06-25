<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\SanctionExport;
use App\Http\Controllers\Controller;
use App\Models\Sanction;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class SanctionsController extends Controller
{
    public function getAllSanctions(Request $request) {
        try {
            $perPage = $request->perPage ?? 10;

            $sanctions = Sanction::query();
            if ($request->has(["column_type", "key_word"])) {
                $sanctions = $sanctions->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $sanctions = $sanctions->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $sanctions = $sanctions->get();
                return Excel::download(new SanctionExport($sanctions), "sanctions.xls");
            }

            $sanctions = $sanctions->paginate($perPage);
            return $this->sendResponse($sanctions);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportSanctions(Request $request) {
        $request["export"] = true;

        return $this->getAllSanctions($request);
    }

    public function getSanctions(Request $request) {
        try {
            $description = Sanction::where("status", "active")->get();
            return $this->sendResponse(["description" => $description]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createSanction(Request $request) {
        try {
            $request->validate([
                "description" => "required|unique:sanctions",
                "suspension_days" => "required",
                "status" => "required",
            ]);

            Sanction::create([
                "description" => $request->description,
                "suspension_days" => $request->suspension_days,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Sanction created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editSanction(Request $request, $id) {
        try {
            $request->validate([
                "description" => "required|unique:sanctions,description,".$id,
                "suspension_days" => "required",
                "status" => "required",
            ]);

            Sanction::where("id", $id)->update([
                "description" => $request->description,
                "suspension_days" => $request->suspension_days,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Sanction updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
