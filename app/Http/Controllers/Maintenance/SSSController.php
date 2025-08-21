<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\SSSExport;
use App\Http\Controllers\Controller;
use App\Models\SSS;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class SSSController extends Controller
{
    public function getAllSSS(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $sss = SSS::query();
            if ($request->has(["column_type", "key_word"])) {
                $sss = $sss->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["export"]) && $request->export) {
                $sss = $sss->get();
                return Excel::download(new SSSExport($sss), "sss.xls");
            }

            $sss = $sss->paginate($perPage);
            return $this->sendResponse($sss);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllSSS(Request $request) {
        $request["export"] = true;

        return $this->getAllSSS($request);
    }

    public function getSSS(Request $request) {
        try {
            $sss = SSS::all();
            return $this->sendResponse(["sss" => $sss]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createSSS(Request $request) {
        try {
            $request->validate([
                "salary_range_min" => "required",
                "salary_range_max" => "required",
                "salary_credit" => "required",
                "employer_ss" => "required",
                "employer_cc" => "required",
                "employee_ss" => "required",
            ]);

            SSS::create([
                "salary_range_min" => $request->salary_range_min,
                "salary_range_max" => $request->salary_range_max,
                "salary_credit" => $request->salary_credit,
                "employer_ss" => $request->employer_ss,
                "employer_cc" => $request->employer_cc,
                "employee_ss" => $request->employee_ss
            ]);

            return $this->sendResponse(["message" => "SSS created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editSSS(Request $request, $id) {
        try {
            $request->validate([
                "salary_range_min" => "required|unique:sss,salary_range_min,".$id,
                "salary_range_max" => "required",
                "salary_credit" => "required",
                "employer_ss" => "required",
                "employer_cc" => "required",
                "employee_ss" => "required",
            ]);

            SSS::where("id", $id)->update([
                "salary_range_min" => $request->salary_range_min,
                "salary_range_max" => $request->salary_range_max,
                "salary_credit" => $request->salary_credit,
                "employer_ss" => $request->employer_ss,
                "employer_cc" => $request->employer_cc,
                "employee_ss" => $request->employee_ss
            ]);

            return $this->sendResponse(["message" => "SSS updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
