<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\PagibigExport;
use App\Http\Controllers\Controller;
use App\Models\Pagibig;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class PagibigController extends Controller
{
    public function getAllPagibig(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $pagibig = Pagibig::query();
            if ($request->has(["column_type", "key_word"])) {
                $pagibig = $pagibig->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["export"]) && $request->export) {
                $pagibig = $pagibig->get();
                return Excel::download(new PagibigExport($pagibig), "pagibig.xls");
            }

            $pagibig = $pagibig->paginate($perPage);
            return $this->sendResponse($pagibig);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllPagibig(Request $request) {
        $request["export"] = true;

        return $this->getAllPagibig($request);
    }

    public function getPagibig(Request $request) {
        try {
            $pagibig = Pagibig::all();
            return $this->sendResponse(["pagibig" => $pagibig]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editPagibig(Request $request, $id) {
        try {
            $request->validate([
                "min_amount" => "required",
                "max_amount" => "required",
                "percentage" => "required",
                "max_deduction" => "required",
            ]);

            Pagibig::where("id", $id)->update([
                "min_amount" => $request->min_amount,
                "max_amount" => $request->max_amount,
                "percentage" => $request->percentage,
                "max_deduction" => $request->max_deduction,
            ]);

            return $this->sendResponse(["message" => "Pagibig updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
