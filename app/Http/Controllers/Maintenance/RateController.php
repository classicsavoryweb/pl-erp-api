<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Rate;
use Illuminate\Http\Request;
use Exception;

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

            $rates = $rates->paginate($perPage);
            return $this->sendResponse($rates);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
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
                "code" => "required",
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
                "code" => "required",
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
