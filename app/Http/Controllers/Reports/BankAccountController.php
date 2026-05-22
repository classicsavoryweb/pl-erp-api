<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\BankAccountHistoryExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BankAccountController extends Controller
{
    public function exportBankAccountHistory(Request $request) {
        try {
            $request->validate([
                "status" => "required"
            ]);

            $employees = Employee::With([
                "bank_details" => function ($q) {
                    $q->latest();
                }
            ]);

            if ($request->status != "all") {
                $employees = $employees->where("status", $request->status);
            }

            $employees = $employees->orderBy("last_name", "ASC")
            ->where("type", "Direct")
            ->get();

            return Excel::download(
                new BankAccountHistoryExport($employees),
                "bank_account_history.xlsx",
                \Maatwebsite\Excel\Excel::XLSX
            );
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
