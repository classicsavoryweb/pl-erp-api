<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\AgencyExport;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class AgencyController extends Controller
{
    public function getAllAgencies(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $agencies = Agency::query();
            if ($request->has(["column_type", "key_word"])) {
               $agencies = $agencies->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $agencies = $agencies->where("status", $request->status);
            }
            
            if ($request->has(["export"]) && $request->export) {
                $agencies = $agencies->get();
                return Excel::download(new AgencyExport($agencies), "agencies.xls");
            }

            $agencies = $agencies->paginate($perPage);
            return $this->sendResponse($agencies);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllAgencies (Request $request) {
        $request["export"] = true;

        return $this->getAllAgencies($request);
    }

    public function getAgencies(Request $request) {
        try {
            $agencies = Agency::where("status", "active")->get();
            return $this->sendResponse(["agencies" => $agencies]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createAgency(Request $request) {
        try {
            $request->validate([
                "name" => "required|unique:agencies",
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

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editAgency(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required|unique:agencies,name,".$id,
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

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
