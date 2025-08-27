<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\AdditionalIncomeExport;
use App\Http\Controllers\Controller;
use App\Models\AdditionalIncome;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class AdditionalIncomeController extends Controller
{
    public function getAllAdditionalIncome(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $additional_income = AdditionalIncome::query();
            if ($request->has(["column_type", "key_word"])) {
                $additional_income = $additional_income->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $additional_income = $additional_income->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $additional_income = $additional_income->get();
                return Excel::download(new AdditionalIncomeExport($additional_income), "additional_income.xls");
            }

            $additional_income = $additional_income->paginate($perPage);
            return $this->sendResponse($additional_income);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllAdditionalIncome(Request $request) {
        $request["export"] = true;

        return $this->getAllAdditionalIncome($request);
    }

    public function getAdditionalIncome(Request $request) {
        try {
            $additional_income = AdditionalIncome::where("status", "active")->get();
            return $this->sendResponse(["additional_income" => $additional_income]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createAdditionalIncome(Request $request) {
        try {
            $request->validate([
                "description" => "required",
                "taxable" => "required",
                "status" => "required",
                "head_office_debit_id" => "required",
                "head_office_credit_id" => "required",
                "store_debit_id" => "required",
                "store_credit_id" => "required",
            ]);

            AdditionalIncome::create([
                "description" => $request->description,
                "taxable" => $request->taxable,
                "status" => $request->status,
                "head_office_debit_id" => $request->head_office_debit_id,
                "head_office_credit_id" => $request->head_office_credit_id,
                "store_debit_id" => $request->store_debit_id,
                "store_credit_id" => $request->store_credit_id,
            ]);

            return $this->sendResponse(["message" => "Additional income created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editAdditionalIncome(Request $request, $id) {
        try {
            $request->validate([
                "description" => "required|unique:additional_income,description,".$id,
                "taxable" => "required",
                "status" => "required",
                "head_office_debit_id" => "required",
                "head_office_credit_id" => "required",
                "store_debit_id" => "required",
                "store_credit_id" => "required",
            ]);

            AdditionalIncome::where("id", $id)->update([
                "description" => $request->description,
                "taxable" => $request->taxable,
                "status" => $request->status,
                "head_office_debit_id" => $request->head_office_debit_id,
                "head_office_credit_id" => $request->head_office_credit_id,
                "store_debit_id" => $request->store_debit_id,
                "store_credit_id" => $request->store_credit_id,
            ]);

            return $this->sendResponse(["message" => "Additional income updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
