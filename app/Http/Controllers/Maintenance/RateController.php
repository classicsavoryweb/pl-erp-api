<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\RateExport;
use App\Http\Controllers\Controller;
use App\Models\Rate;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class RateController extends Controller
{
    public function getAllRates(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $rates = Rate::query();

            if ($request->has(["column_type", "key_word"])) {
               $rates = $rates->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $rates = $rates->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $rates = $rates->get();
                return Excel::download(new RateExport($rates), "rates.xls");
            }

            $rates = $rates->paginate($perPage);
            return $this->sendResponse($rates);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllRates (Request $request) {
        $request["export"] = true;

        return $this->getAllRates($request);
    }

    public function getRates(Request $request) {
        try {
            $rates = Rate::where("status", "active")->get();
            return $this->sendResponse(["rates" => $rates]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createRate(Request $request) {
        try {
            $request->validate([
                "code" => "required|unique:rates",
                "area" => "required",
                "minimum" => "required",
                "status" => "required",
            ]);

            Rate::create([
                "code" => $request->code,
                "area" => str_replace(["\\r\\n", "\\n", "\\r"], PHP_EOL, $request->area),
                "minimum" => $request->minimum,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Rate created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editRate(Request $request, $id) {
        try {
            $request->validate([
                "code" => "required|unique:rates,code,".$id,
                "area" => "required",
                "minimum" => "required",
                "status" => "required",
            ]);

            Rate::where("id", $id)->update([
                "code" => $request->code,
                "area" => str_replace(["\\r\\n", "\\n", "\\r"], PHP_EOL, $request->area),
                "minimum" => $request->minimum,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Rate updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
