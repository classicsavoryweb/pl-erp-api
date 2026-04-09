<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\PayrollProofListExport;
use App\Http\Controllers\Controller;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Incentives;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollList;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
                "bank_account_type_id" => "required",
                "branch_id" => "nullable"
            ]);

            $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $request->year);
            $start_time = $dates["start_date"];
            $end_time = $dates["end_date"];

            $cutOff = Cutoff::where("start_date", $start_time)
            ->where("end_date", $end_time)
            ->first();

            $model = new Payroll();
            $table = "payroll";
            if ($request->type === "INCENTIVES") {
                $model = new Incentives();
                $table = "incentives";
            }

            if (!$cutOff) {
                return $this->sendError(["message" => "Cutoff not yet initialized"]);
            }

            $proofList = $model::where("$table.pay_date",$cutOff->pay_date)
            ->leftJoin("employees", "employees.id", "=", "$table.employee_id")
            ->leftJoin("payroll_list", "payroll_list.employee_id", "=", "$table.employee_id")
            ->leftJoin("bank_details", "payroll_list.bank_details_id", "=", "bank_details.id")
            ->leftJoin("bank_account_type", "bank_details.bank_account_type_id", "=", "bank_account_type.id")
            ->leftJoin("payroll_details", function ($query) use($table, $cutOff) {
                $query->on("payroll_details.employee_id", "=", "$table.employee_id")
                ->where("payroll_details.pay_date", $cutOff->pay_date);
            });

            if ($request->bank_account_type_id == '0') {
                $proofList = $proofList->where("is_bank", 0);
            }

            if (in_array($request->bank_account_type_id, ['1', '2'])) {
                $proofList = $proofList->whereHas("employee.payroll_list.current_bank_details", function ($query) use($request) {
                    if (in_array($request->bank_account_type_id, ['1', '2'])) {
                        $query->where("bank_account_type_id", $request->bank_account_type_id);
                    }
                });
            }

            $proofList = $proofList->leftJoin('branches as b', 'payroll.timekeeping_branch_id', '=', 'b.id',)
                ->leftJoin('positions as p', 'payroll.position_id', '=', 'p.id');

            if ($request->type === "INCENTIVES") {
                $proofList = $proofList->addSelect(
                    'payroll_list.*',
                    DB::raw('incentives.incentive_amount as amount'), 
                    'incentives.*',
                    'employees.last_name',
                    'employees.first_name',
                    'employees.middle_name',
                    'employees.employee_number',
                    "bank_details.bank_number",
                    "bank_account_type.description as bank_account_type",
                    'p.description as position',
                );

            } else {
                $proofList = $proofList->addSelect(
                    'payroll_list.*',
                    DB::raw('payroll.net_pay as amount'),
                    'payroll.*',
                    'payroll_details.*',
                    'employees.last_name',
                    'employees.first_name',
                    'employees.middle_name',
                    'employees.employee_number',
                    'b.name as branch_name',
                    'b.short_name',
                    'payroll_details.cancelled_off as cancelled_off_hours',
                    'payroll.cancelled_off as cancelled_off_amount',
                    'payroll_details.special_leave as special_leave_days',
                    'payroll.special_leave as special_leave_amount',
                    'p.description as position',
                    "bank_details.bank_number",
                    "bank_account_type.description as bank_account_type"
                );
            }

            if ($request->export === true) {
                return $proofList->get();
            }

            $proofList = $proofList->paginate(10);

            return $this->sendResponse($proofList);
        } catch (Exception $e) {
            Log::info($e);
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

            // if ($cutOff && $cutOff->is_cutoff_locked !== 1 && $cutOff->status === "active") {
            //     return $this->sendError(["message" => "You cant generate unfinalized cutoff"], 409);
            // }

            $request["export"] = true;
            $proofLists = $this->getProofList($request);

            if ($proofLists instanceof \Illuminate\Http\JsonResponse) {
                return $proofLists;
            }

            if (!($proofLists instanceof \Illuminate\Support\Collection)) {
                return $this->sendError(["message" => "Unable to build prooflist data for converter export."]);
            }

            $converterToUse = $request->bank_account_type_id == 1 ? "CASH_CARD_CONVERTER" : "SAVINGS_CONVERTER";
            $templatePath = public_path('converter/'.$converterToUse.'.xlsm');
            $tempCopyPath = storage_path('app/public/'.$request->type.'-'.now()->format("Y-m-d").'.xlsm');

            if (!file_exists($templatePath)) {
                return $this->sendError(["message" => "Converter template file not found."]);
            }

            if (!copy($templatePath, $tempCopyPath)) {
                return $this->sendError(["message" => "Failed to prepare converter export file."]);
            }

            
            $company = env('COMPANY_FULL_NAME');
            $filePrefix =  env('COMPANY_NAME') == "SFI" ? "TPAYSAVORY" : "TPAYTONYTI";
            $virtualAccount =  env('COMPANY_NAME') == "SFI" ? "9049948528" : "9081473203";
            $companyCode = env('COMPANY_NAME') =="SFI" ? "X48Z" : "Y490";
            $creditingDate = now()->format("Y-m-d");

            $zip = new ZipArchive;
            $openResult = $zip->open($tempCopyPath);
            if ($openResult !== TRUE) {
                if (file_exists($tempCopyPath)) {
                    @unlink($tempCopyPath);
                }

                return $this->sendError(["message" => "Failed to open converter export file."]);
            }

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
            $writtenRows = 0;
            foreach ($proofLists as $transaction) {
                $fullName = trim(($transaction->last_name ?? '') . ", " . ($transaction->first_name ?? '') . " " . ($transaction->middle_name ?? ''));
                $bankNumber = $transaction->bank_number;
                $bankNumber = str_replace(' ', '', $bankNumber);

                if (
                    $transaction->is_hold == 1 ||
                    $bankNumber === '' ||
                    $fullName === '' ||
                    $transaction->is_bank == 0 ||
                    $transaction->amount <= 0 ||
                    $transaction->is_on_maternity_leave == 1 
                ) {
                    continue;
                }

                $colA = "A".$rowIndex;
                $colB = "B".$rowIndex;
                $colC = "C".$rowIndex;

                $this->writeCell($dom, $sheetData, $colA, $bankNumber);
                $this->writeCell($dom, $sheetData, $colB, (string)round($transaction->amount ?? 0, 2));
                $this->writeCell($dom, $sheetData, $colC, $fullName);

                $rowIndex++;
                $writtenRows++;
            }

            $newXml = $dom->saveXML();
            $zip->addFromString('xl/worksheets/sheet1.xml', $newXml);
            $zip->close();

            return response()->download($tempCopyPath, $request->type."-".now()->format("Y-m-d").'.xlsm')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::info($e);
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
