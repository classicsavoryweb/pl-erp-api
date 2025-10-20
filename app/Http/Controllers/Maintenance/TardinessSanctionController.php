<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\TardinessSanctionExport;
use App\Http\Controllers\Controller;
use App\Models\TardinessSanction;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TardinessSanctionController extends Controller
{
    public function getAllTardinessSanctions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $tardinessSanctions = TardinessSanction::query();
            if ($request->has(["column_type", "key_word"])) {
               $tardinessSanctions = $tardinessSanctions->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $tardinessSanctions = $tardinessSanctions->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $tardinessSanctions = $tardinessSanctions->get();
                return Excel::download(new TardinessSanctionExport($tardinessSanctions), "tardiness_sanctions.xls");
            }

            $tardinessSanctions = $tardinessSanctions->paginate($perPage);
            return $this->sendResponse($tardinessSanctions);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTardinessSanctions(Request $request) {
        $request["export"] = true;

        return $this->getAllTardinessSanctions($request);
    }

    public function createTardinessSanctions(Request $request) {
        try {
            $request->validate([
                "count" => "required|unique:tardiness_sanctions",
                "punishment" => "required",
                "rank" => "required",
                "incentives_deduction" => "required",
                "status" => "required"
            ]);
            TardinessSanction::create([
                "count" => $request->count,
                "punishment" => $request->punishment,
                "rank" => $request->rank,
                "incentives_deduction" => $request->incentives_deduction,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Tardiness created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editTardinessSanctions(Request $request, $id) {
        try {
            $request->validate([
                "count" => "required|unique:tardiness_sanctions,count,".$id,
                "punishment" => "required",
                "rank" => "required",
                "incentives_deduction" => "required",
                "status" => "required"
            ]);
            $tardinessSanction = TardinessSanction::find($id);

            $tardinessSanction->update([
                "count" => $request->count,
                "punishment" => $request->punishment,
                "rank" => $request->rank,
                "incentives_deduction" => $request->incentives_deduction,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Tardiness updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
