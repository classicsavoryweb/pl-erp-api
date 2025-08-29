<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\CSHubTransactionTypeExport;
use App\Http\Controllers\Controller;
use App\Models\CSHubTransactionType;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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
 
            $transactionsTypes = $transactionsTypes->paginate($perPage);

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
}
