<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\TaxExport;
use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class TaxController extends Controller
{
    public function getAllTax(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $tax = Tax::query();
            if ($request->has(["column_type", "key_word"])) {
                $tax = $tax->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["export"]) && $request->export) {
                $tax = $tax->get();
                return Excel::download(new TaxExport($tax), "tax.xls");
            }

            $tax = $tax->paginate($perPage);
            return $this->sendResponse($tax);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTax(Request $request) {
        $request["export"] = true;

        return $this->getAllTax($request);
    }

    public function getTax(Request $request) {
        try {
            $tax = Tax::all();
            return $this->sendResponse(["tax" => $tax]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createTax(Request $request) {
        try {
            $request->validate([
                "type" => "required",
                "status" => "required",
                "range_min" => "required",
                "range_max" => "required",
                "salary_base" => "required",
                "percentage" => "required",
                "exemption" => "required",
            ]);

            Tax::create([
                "type" => $request->type,
                "status" => $request->status,
                "range_min" => $request->range_min,
                "range_max" => $request->range_max,
                "salary_base" => $request->salary_base,
                "percentage" => $request->percentage,
                "exemption" => $request->exemption
            ]);

            return $this->sendResponse(["message" => "Tax created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editTax(Request $request, $id) {
        try {
            $request->validate([
                "type" => "required",
                "status" => "required",
                "range_min" => "required",
                "range_max" => "required",
                "salary_base" => "required",
                "percentage" => "required",
                "exemption" => "required",
            ]);

            Tax::where("id", $id)->update([
                "type" => $request->type,
                "status" => $request->status,
                "range_min" => $request->range_min,
                "range_max" => $request->range_max,
                "salary_base" => $request->salary_base,
                "percentage" => $request->percentage,
                "exemption" => $request->exemption
            ]);

            return $this->sendResponse(["message" => "Tax updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
