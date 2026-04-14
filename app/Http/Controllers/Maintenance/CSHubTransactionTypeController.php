<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\CSHubTransactionTypeExport;
use App\Http\Controllers\Controller;
use App\Models\CSHubTransactionType;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use DB;

class CSHubTransactionTypeController extends Controller
{
    public function getAllCsHubTransactionTypes(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $transactionsTypes = CSHubTransactionType::query();

            if ($request->has(["column_type", "key_word"])) {
               $transactionsTypes = $transactionsTypes->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $transactionsTypes = $transactionsTypes->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $transactionsTypes = $transactionsTypes->get();
                return Excel::download(new CSHubTransactionTypeExport($transactionsTypes), "transactionsTypes.xls");
            }
 
            $transactionsTypes = $transactionsTypes
            ->with(["module", "deduction", "trans_type", "unique_exam_type"])
            ->paginate($perPage);

            return $this->sendResponse($transactionsTypes);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportCsHubTransactionTypes(Request $request) {
        $request["export"] = true;

        return $this->getAllCsHubTransactionTypes($request);
    }

    public function getCsHubTransactionTypes(Request $request) {
        try {
            $transactionTypes = CSHubTransactionType::where("status", "active")->get();
            return $this->sendResponse(["transaction_types" => $transactionTypes]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createCSHubTransactionType(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "description" => "required",
                "source_id" => "required",
                "transaction_type" => "required",
                "deduction_id" => "nullable",
                "ho_credit" => "required",
                "branch_debit" => "nullable",
                "branch_credit" => "nullable",
                "status" => "required",
            ]);

            CSHubTransactionType::create([
                "description" => $request->description,
                "source_id" => $request->source_id,
                "transaction_type" => $request->transaction_type,
                "exam_type" => $request->exam_type,
                "deduction_id" => $request->deduction_id,
                "deduction_status_positive" => $request->deduction_status_positive,
                "deduction_status_negative" => $request->deduction_status_negative,
                "credit_status_positive" => $request->credit_status_positive,
                "credit_status_negative" => $request->credit_status_negative,
                "batch_entry" => $request->batch_entry,
                "email_status" => $request->email_status,
                "ho_debit" => $request->ho_debit,
                "ho_credit" => $request->ho_credit,
                "branch_debit" => $request->branch_debit,
                "branch_credit" => $request->branch_credit,
                "advances_debit" => $request->advances_debit,
                "status" => $request->status,
            ]);
            DB::commit();
            return $this->sendResponse(["message" => "Transaction type created succesfully"]);
        } catch(Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editCSHubTransactionType(Request $request, $id) {
        try {
            DB::beginTransaction();
            $request->validate([
                "description" => "required|unique:cs_hub_transaction_type,description,".$id,
                "source_id" => "required",
                "transaction_type" => "required",
                "deduction_id" => "nullable",
                "ho_credit" => "required",
                "branch_debit" => "nullable",
                "branch_credit" => "nullable",
                "status" => "required",
            ]);

            CSHubTransactionType::where("id", $id)->update([
                "description" => $request->description,
                "source_id" => $request->source_id,
                "transaction_type" => $request->transaction_type,
                "exam_type" => $request->exam_type,
                "deduction_id" => $request->deduction_id,
                "deduction_status_positive" => $request->deduction_status_positive,
                "deduction_status_negative" => $request->deduction_status_negative,
                "credit_status_positive" => $request->credit_status_positive,
                "credit_status_negative" => $request->credit_status_negative,
                "batch_entry" => $request->batch_entry,
                "email_status" => $request->email_status,
                "ho_debit" => $request->ho_debit,
                "ho_credit" => $request->ho_credit,
                "branch_debit" => $request->branch_debit,
                "branch_credit" => $request->branch_credit,
                "advances_debit" => $request->advances_debit,
                "status" => $request->status,
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Transaction type updated succesfully"]);
        } catch(Exception $e) {

            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
