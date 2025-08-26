<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\DeductionExport;
use App\Http\Controllers\Controller;
use App\Models\Deduction;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class DeductionsController extends Controller
{
    public function getAllDeductions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $deductions = Deduction::query();
            if ($request->has(["column_type", "key_word"])) {
                $deductions = $deductions->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $deductions = $deductions->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $deductions = $deductions->get();
                return Excel::download(new DeductionExport($deductions), "deductions.xls");
            }

            $deductions = $deductions->paginate($perPage);
            return $this->sendResponse($deductions);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllDeductions(Request $request) {
        $request["export"] = true;

        return $this->getAllDeductions($request);
    }

    public function getDeduction(Request $request) {
        try {
            $deductions = Deduction::where("status", "Active")->get();
            return $this->sendResponse(["deductions" => $deductions]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createDeduction(Request $request) {
        try {
            $request->validate([
                "description" => "required",
                "debit_id" => "required",
                "credit_id" => "required",
                "amortization_vendor_id" => "required",
                "amortization_debit_id" => "required",
                "amortization_credit_id" => "required",
                "status" => "required",
                "end_loan_vendor_id" => "required",
                "end_loan_debit_id" => "required",
                "end_load_credit_id" => "required",
            ]);

            Deduction::create([
                "description" => $request->description,
                "debit_id" => $request->debit_id,
                "credit_id" => $request->credit_id,
                "amortization_vendor_id" => $request->amortization_vendor_id,
                "amortization_debit_id" => $request->amortization_debit_id,
                "amortization_credit_id" => $request->amortization_credit_id,
                "status" => $request->status,
                "end_loan_vendor_id" => $request->end_loan_vendor_id,
                "end_loan_debit_id" => $request->end_loan_debit_id,
                "end_load_credit_id" => $request->end_load_credit_id,
            ]);

            return $this->sendResponse(["message" => "Deduction created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editDeduction(Request $request, $id) {
        try {
            $request->validate([
                "description" => "required|unique:deductions,description,".$id,
                "debit_id" => "required",
                "credit_id" => "required",
                "amortization_vendor_id" => "required",
                "amortization_debit_id" => "required",
                "amortization_credit_id" => "required",
                "status" => "required",
                "end_loan_vendor_id" => "required",
                "end_loan_debit_id" => "required",
                "end_load_credit_id" => "required",
            ]);

            Deduction::where("id", $id)->update([
                "description" => $request->description,
                "debit_id" => $request->debit_id,
                "credit_id" => $request->credit_id,
                "amortization_vendor_id" => $request->amortization_vendor_id,
                "amortization_debit_id" => $request->amortization_debit_id,
                "amortization_credit_id" => $request->amortization_credit_id,
                "status" => $request->status,
                "end_loan_vendor" => $request->end_loan_vendor_id,
                "end_loan_debit_id" => $request->end_loan_debit_id,
                "end_load_credit_id" => $request->end_load_credit_id,
            ]);

            return $this->sendResponse(["message" => "Deduction updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
