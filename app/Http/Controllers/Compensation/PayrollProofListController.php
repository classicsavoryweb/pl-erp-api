<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\Cutoff;
use App\Models\Incentives;
use App\Models\Payroll;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;

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

            $start_time = $request->year . '-' . $request->cutoff["start_time"];
            $end_time = $request->year . '-' . $request->cutoff["end_time"];

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
            ->where("pay_date", $cutOff->pay_date)
            ->whereHas("employee", function ($query) use ($request) {
                $query->whereHas("latest_bank_details", function ($q) use ($request) {
                    $q->where("bank_account_type_id", $request->bank_account_type_id);
                });
            });

            if ($request->type === "INCENTIVES") {
                $proofList = $proofList->select([
                    "status",
                    "id",
                    "employee_id",
                    "incentive_amount as amount"
                ])->where("incentive_amount", ">", 0);
            } else {
                $proofList = $proofList->select([
                    "status",
                    "id",
                    "employee_id",
                    "net_pay as amount"
                ])->where("net_pay", ">", 0);
            }

            if ($request->export === true) {
                $proofList = $proofList->get();
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

            $request["export"] = true;
            $proofLists = $this->getProofList($request);

            $converterToUse = $request->bank_account_type_id == 1 ? "CASH_CARD_CONVERTER" : "SAVINGS_CONVERTER";
            $templatePath = public_path('converter/'.$converterToUse.'.xlsm');
            $tempCopyPath = storage_path('app/public/'.$request->type.'-'.now()->format("Y-m-d").'.xlsm');
            copy($templatePath, $tempCopyPath);

            $zip = new ZipArchive;
            if ($zip->open($tempCopyPath) === TRUE) {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

                $dom = new DOMDocument('1.0', 'UTF-8');
                $dom->loadXML($sheetXml);
                $sheetData = $dom->getElementsByTagName('sheetData')->item(0);

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
}
