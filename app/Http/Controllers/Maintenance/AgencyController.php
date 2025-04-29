<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\Request;
use Exception;

class AgencyController extends Controller
{
    public function getAllAgencies(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $agencies = Agency::paginate($perPage);
            return $this->sendResponse($agencies);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function getAgencies(Request $request) {
        try {
            $agencies = Agency::where("status", "active")->get();
            return $this->sendResponse(["agencies" => $agencies]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function createAgency(Request $request) {
        try {
            $request->validate([
                "name" => "required",
                "status" => "required",
                "employee_code" => "required",
                "address" => "required"
            ]);

            Agency::create([
                "name" => $request->name,
                "status" => $request->status,
                "employee_code" => $request->employee_code,
                "address" => $request->address
            ]);

            return $this->sendResponse(["message" => "Agency created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function editAgency(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required",
                "status" => "required",
                "employee_code" => "required",
                "address" => "required"
            ]);

            Agency::where("id", $id)->update([
                "name" => $request->name,
                "status" => $request->status,
                "address" => $request->address,
                "employee_code" => $request->employee_code
            ]);

            return $this->sendResponse(["message" => "Agency created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }
}
