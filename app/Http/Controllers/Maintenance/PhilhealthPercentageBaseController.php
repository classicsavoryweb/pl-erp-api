<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\PhilhealthPercentageBase;
use Illuminate\Http\Request;
use Exception;

class PhilhealthPercentageBaseController extends Controller
{

    public function getPhilhealthPercentageBase(Request $request) {
        try {
            $philhealth_percentage_base = PhilhealthPercentageBase::all();
            return $this->sendResponse($philhealth_percentage_base);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editPhilhealthPercentageBase(Request $request, $id) {
        try {
            $request->validate([
                "philhealth_formula" => "required",
                "percentage" => "required",
                "philhealth_cap" => "required",
                "philhealh_max" => "required"
            ]);

            PhilhealthPercentageBase::where("id", $id)->update([
                "philhealth_formula" => $request->philhealth_formula,
                "percentage" => $request->percentage,
                "philhealth_cap" => $request->philhealth_cap,
                "philhealth_max" => $request->philhealth_max,
            ]);

            return $this->sendResponse(["message" => "Philhealth percentage base updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
