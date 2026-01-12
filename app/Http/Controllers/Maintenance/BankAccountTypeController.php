<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\BankAccountExport;
use App\Http\Controllers\Controller;
use App\Models\BankAccountType;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
class BankAccountTypeController extends Controller
{
    public function getAllBankAccountTypes(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $bankAccountTypes = BankAccountType::query();
            
            if ($request->has(["column_type", "key_word"])) {
               $bankAccountTypes = $bankAccountTypes->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $bankAccountTypes = $bankAccountTypes->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $bankAccountTypes = $bankAccountTypes->get();
                return Excel::download(new BankAccountExport($bankAccountTypes), "bank_account_type.xls");
            }

            $bankAccountTypes = $bankAccountTypes->paginate($perPage);
            return $this->sendResponse($bankAccountTypes);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllBankAccountTypes(Request $request) {
        $request["export"] = true;

        return $this->getAllBankAccountTypes($request);
    }

    public function getBankAccountTypes(Request $request) {
        try {
            $rates = BankAccountType::where("status", "active")->get();
            return $this->sendResponse(["bank_account_types" => $rates]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createBankAccountType(Request $request) {
        try {
            $request->validate([
                "description" => "required|unique:bank_account_type",
                "number_length" => "required",
                "status" => "required",
            ]);

            BankAccountType::create([
                "description" => $request->description,
                "number_length" => $request->number_length,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Bank account type created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editBankAccountType(Request $request, $id) {
        try {
            $request->validate([
                "description" => "required|unique:bank_account_type,description,".$id,
                "number_length" => "required",
                "status" => "required",
            ]);

            BankAccountType::where("id", $id)->update([
                "description" => $request->description,
                "number_length" => $request->number_length,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Bank account type updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
