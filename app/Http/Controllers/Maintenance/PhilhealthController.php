<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\PhilhealthExport;
use App\Http\Controllers\Controller;
use App\Models\Philhealth;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class PhilhealthController extends Controller
{
    public function getAllPhilhealth(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $philhealth = Philhealth::query();
            if ($request->has(["column_type", "key_word"])) {
                $philhealth = $philhealth->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["export"]) && $request->export) {
                $philhealth = $philhealth->get();
                return Excel::download(new PhilhealthExport($philhealth), "philhealth.xls");
            }

            $philhealth = $philhealth->paginate($perPage);
            return $this->sendResponse($philhealth);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllPhilhealth(Request $request) {
        $request["export"] = true;

        return $this->getAllPhilhealth($request);
    }

    public function getPhilhealth(Request $request) {
        try {
            $philhealth = Philhealth::all();
            return $this->sendResponse(["philhealth" => $philhealth]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createPhilhealth(Request $request) {
        try {
            $request->validate([
                "salary_range_min" => "required",
                "salary_range_max" => "required",
                "salary_base" => "required",
                "employee_share" => "required",
                "employer_share" => "required",
                "total_monthly_contribution" => "required",
            ]);

            Philhealth::create([
                "salary_range_min" => $request->salary_range_min,
                "salary_range_max" => $request->salary_range_max,
                "salary_base" => $request->salary_base,
                "employee_share" => $request->employee_share,
                "employer_share" => $request->employer_share,
                "total_monthly_contribution" => $request->total_monthly_contribution
            ]);

            return $this->sendResponse(["message" => "Philhealth created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editPhilhealth(Request $request, $id) {
        try {
            $request->validate([
                "salary_range_min" => "required|unique:philhealth,salary_range_min,".$id,
                "salary_range_max" => "required",
                "salary_base" => "required",
                "employee_share" => "required",
                "employer_share" => "required",
                "total_monthly_contribution" => "required",
            ]);

            Philhealth::where("id", $id)->update([
                "salary_range_min" => $request->salary_range_min,
                "salary_range_max" => $request->salary_range_max,
                "salary_base" => $request->salary_base,
                "employee_share" => $request->employee_share,
                "employer_share" => $request->employer_share,
                "total_monthly_contribution" => $request->total_monthly_contribution
            ]);

            return $this->sendResponse(["message" => "Philhealth updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
