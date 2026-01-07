<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\PayrollProofListExport;
use App\Http\Controllers\Controller;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Incentives;
use App\Models\Payroll;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Maatwebsite\Excel\Facades\Excel;

class PayrollProofListController extends Controller
{
    public function getProofList(Request $request) {
        try {
            $request->validate([
                "cutoff" => "required|array",
                "year" => "required",
                "type" => "required",
                "bank_account_type_id" => "required"
            ]);

            $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $request->year);
            $start_time = $dates["start_date"];
            $end_time = $dates["end_date"];

            $cutOff = Cutoff::where("start_date", $start_time)
            ->where("end_date", $end_time)
            ->first();

            if (!$cutOff) {
                return $this->sendError(["message" => "Cutoff not yet initialized"]);
            }

            $proofList = Payroll::query();

            if ($request->type === "INCENTIVES") {
                $proofList = Incentives::query();
            }

            $proofList = $proofList->with(["employee"])
            ->where("pay_date", $cutOff->pay_date);

            if ($request->type === "INCENTIVES") {
                $proofList = $proofList->select([
                    "status",
                    "id",
                    "employee_id",
                    "adjustment_percent",
                    "additional_incentive",
                    "incentive_amount as amount",
                    "first_cut_off_worked_days",
                    "second_cut_off_worked_days",
                ])->where("incentive_amount", ">", 0)
                ->orderBy(
                    Employee::select("last_name")
                        ->whereColumn("employees.id", "incentives.employee_id")
                );
            } else {
                $proofList = $proofList->select([
                    "status",
                    "id",
                    "employee_id",
                    "net_pay as amount"
                ])->where("net_pay", ">", 0)
                ->orderBy(
                    Employee::select("last_name")
                        ->whereColumn("employees.id", "payroll.employee_id")
                );
            }

            if ($request->bank_account_type_id !== '0') {
                $proofList = $proofList->whereHas("employee", function ($query) use ($request) {
                    $query->whereHas("latest_bank_details", function ($q) use ($request) {
                        $q->where("bank_account_type_id", $request->bank_account_type_id);
                    });
                });
            } else {
                $proofList = $proofList->whereHas("payroll_list", function ($query) {
                    $query->where("is_bank", false);
                });
            }

            if ($request->export === true) {
                $proofList = $proofList->whereHas("payroll_list", function ($query) {
                    $query->where("is_hold", false);
                })->get();
                return $proofList;
            }

            $proofList = $proofList->paginate(10);

            return $this->sendResponse($proofList);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function generateConverterForProoflist (Request $request) {
        try {
            $request->validate([
                "cutoff" => "required|array",
                "year" => "required",
                "type" => "required",
                "bank_account_type_id" => "required"
            ]);

            $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $request->year);
            $start_time = $dates["start_date"];
            $end_time = $dates["end_date"];

            $cutOff = Cutoff::where("start_date", $start_time)
            ->where("end_date", $end_time)
            ->first();

            if (!$cutOff || $cutOff->status !== "active") {
                return $this->sendError(["message" => "You cant generate inactive cutoff"], 404);
            }

            if ($cutOff && $cutOff->is_cutoff_locked !== 1 && $cutOff->status === "active") {
                return $this->sendError(["message" => "You cant generate unfinalized cutoff"], 409);
            }

            $request["export"] = true;
            $proofLists = $this->getProofList($request);

            $converterToUse = $request->bank_account_type_id == 1 ? "CASH_CARD_CONVERTER" : "SAVINGS_CONVERTER";
            $templatePath = public_path('converter/'.$converterToUse.'.xlsm');
            $tempCopyPath = storage_path('app/public/'.$request->type.'-'.now()->format("Y-m-d").'.xlsm');
            copy($templatePath, $tempCopyPath);

            
            $company = env('COMPANY_FULL_NAME');
            $filePrefix =  env('COMPANY_NAME') == "SFI" ? "TPAYSAVORY" : "TPAYTONYTI";
            $virtualAccount =  env('COMPANY_NAME') == "SFI" ? "9049948528" : "9081473203";
            $companyCode = env('COMPANY_NAME') =="SFI" ? "X48Z" : "Y490";
            $creditingDate = now()->format("Y-m-d");

            $zip = new ZipArchive;
            if ($zip->open($tempCopyPath) === TRUE) {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

                $dom = new DOMDocument('1.0', 'UTF-8');
                $dom->loadXML($sheetXml);
                $sheetData = $dom->getElementsByTagName('sheetData')->item(0);

                $this->writeCell($dom, $sheetData, 'A1', (string)$company);
                if ($request->bank_account_type_id == 1) {
                    $this->writeCell($dom, $sheetData, 'B2', (string)$filePrefix);
                    $this->writeCell($dom, $sheetData, 'B3', (string)$virtualAccount);
                    $this->writeCell($dom, $sheetData, 'B4', (string)$creditingDate);
                } else {
                    $this->writeCell($dom, $sheetData, 'B2', (string)$creditingDate);
                    $this->writeCell($dom, $sheetData, 'B3', (string)$companyCode);
                }

                $rowIndex = 7;
                foreach ($proofLists as $transaction) {
                    $colA = "A".$rowIndex;
                    $colB = "B".$rowIndex;
                    $colC = "C".$rowIndex;

                    $this->writeCell($dom, $sheetData, $colA, (string)str_replace(' ', '', $transaction->employee->latest_bank_details->bank_number));
                    $this->writeCell($dom, $sheetData, $colB, (string)round($transaction->amount, 2));
                    $this->writeCell($dom, $sheetData, $colC, (string)$transaction->employee->full_name);

                    $rowIndex++;
                }

                $newXml = $dom->saveXML();
                $zip->addFromString('xl/worksheets/sheet1.xml', $newXml);
                $zip->close();
            }

            return response()->download($tempCopyPath, $request->type."-".now()->format("Y-m-d").'.xlsm')->deleteFileAfterSend(true);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function exportProoflist(Request $request) {
        try {
            $request->validate([
                "cutoff" => "required|array",
                "year" => "required",
                "type" => "required",
                "bank_account_type_id" => "required"
            ]);

            $request["export"] = true;
            $proofLists = $this->getProofList($request);

            return Excel::download(new PayrollProofListExport($proofLists, $request->type), "prooflist.xls");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
