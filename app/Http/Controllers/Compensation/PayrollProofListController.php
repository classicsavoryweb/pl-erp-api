<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\PayrollProofListExport;
use App\Http\Controllers\Controller;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Incentives;
use App\Models\Payroll;
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
            $proofList = $proofList->with([
            "employee.latest_bank_details.bank_account_type", 
            ]);

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
            } else if ($request->type === "PAYROLL" && !$request->export) {
                $proofList = PayrollList::query()
                ->with(["employee"])
                ->where("is_included", 1)
                ->leftJoin("payroll as p", function ($join) use ($cutOff) {
                    $join->on("p.employee_id", "=", "payroll_list.employee_id")
                        ->where("p.pay_date", "=", $cutOff->pay_date);
                })
                ->select([
                    "payroll_list.*",
                    DB::raw("p.id as payroll_id"),
                    DB::raw("COALESCE(p.status, 'no-payroll') as status"),
                    DB::raw("COALESCE(p.net_pay, 0) as amount"),
                    DB::raw("p.cut_off as cut_off"),
                ])
                ->orderBy(
                    Employee::select("last_name")
                        ->whereColumn("employees.id", "payroll_list.employee_id")
                );
            } else if ($request->type === "PAYROLL" && $request->export && !$request->converter) {
                $proofList = PayrollList::query()
                ->with([
                    "employee",
                    "employee.current_branch",
                    "employee.home_branch",
                    "employee.latest_bank_details.bank_account_type",
                    "employee.schedule.schedule_type",
                    "employee.payroll_list"
                ])
                ->where("is_included", 1)
                ->leftJoin("payroll as p", function ($join) use ($cutOff) {
                    $join->on("p.employee_id", "=", "payroll_list.employee_id")
                        ->where("p.pay_date", "=", $cutOff->pay_date);
                })
                ->leftJoin("timekeeping_summary as ts", function ($join) {
                    $join->on("ts.employee_id", "=", "payroll_list.employee_id")
                        ->on("ts.cut_off", "=", "p.cut_off");
                })
                ->leftJoin("branches as tb", "tb.id", "=", "p.timekeeping_branch_id")
                ->select([
                    "payroll_list.*",
                    DB::raw("p.id as payroll_id"),
                    DB::raw("COALESCE(p.status, 'no-payroll') as status"),
                    DB::raw("COALESCE(p.net_pay, 0) as amount"),
                    DB::raw("p.cut_off as cut_off"),
                    DB::raw("p.daily_rate as daily_rate"),
                    DB::raw("p.hourly_rate as hourly_rate"),
                    DB::raw("p.tax_status as tax_status"),
                    DB::raw("p.salary as salary"),
                    DB::raw("p.cancelled_off as cancelled_off"),
                    DB::raw("p.special_holiday_amount as special_holiday_amount"),
                    DB::raw("p.legal_holiday_amount as legal_holiday_amount"),
                    DB::raw("p.overtime_amount as overtime_amount"),
                    DB::raw("p.legal_holiday_overtime_amount as legal_holiday_overtime_amount"),
                    DB::raw("p.special_holiday_overtime_amount as special_holiday_overtime_amount"),
                    DB::raw("p.night_differential_amount as night_differential_amount"),
                    DB::raw("p.night_differential_overtime_amount as night_differential_overtime_amount"),
                    DB::raw("p.tardiness_amount as tardiness_amount"),
                    DB::raw("p.undertime_amount as undertime_amount"),
                    DB::raw("p.taxable_adjustment as taxable_adjustment"),
                    DB::raw("p.non_taxable_adjustment as non_taxable_adjustment"),
                    DB::raw("p.gross_pay as gross_pay"),
                    DB::raw("p.employer_sss as employer_sss"),
                    DB::raw("p.employer_philhealth as employer_philhealth"),
                    DB::raw("p.employer_pagibig as employer_pagibig"),
                    DB::raw("p.employee_sss as employee_sss"),
                    DB::raw("p.employee_philhealth as employee_philhealth"),
                    DB::raw("p.employee_pagibig as employee_pagibig"),
                    DB::raw("p.taxable_income as taxable_income"),
                    DB::raw("p.withholding_tax as withholding_tax"),
                    DB::raw("p.total_deductions as total_deductions"),
                    DB::raw("tb.name as timekeeping_branch_name"),
                    DB::raw("COALESCE(tb.short_name, tb.branch_number) as timekeeping_branch_code"),
                    DB::raw("ts.regular_days as regular_days"),
                    DB::raw("ts.cancelled_off as cancelled_off_hours"),
                    DB::raw("ts.special_holiday as special_holiday"),
                    DB::raw("ts.legal_holiday as legal_holiday"),
                    DB::raw("ts.regular_overtime as regular_overtime"),
                    DB::raw("ts.legal_holiday_overtime as legal_holiday_overtime"),
                    DB::raw("ts.special_holiday_overtime as special_holiday_overtime"),
                    DB::raw("ts.night_differential as night_differential"),
                    DB::raw("ts.night_differential_overtime as night_differential_overtime"),
                    DB::raw("ts.tardiness as tardiness"),
                    DB::raw("ts.undertime as undertime"),
                ])
                ->orderBy(
                    Employee::select("last_name")
                        ->whereColumn("employees.id", "payroll_list.employee_id")
                );
            } else if ($request->type === "PAYROLL" && $request->export && $request->converter) {
                $proofList = $proofList->select([
                    "status",
                    "id",
                    "employee_id",
                    "net_pay as amount",
                    "cut_off", 
                ])->where("net_pay", ">", 0)
                ->orderBy(
                    Employee::select("last_name")
                        ->whereColumn("employees.id", "payroll.employee_id")
                );
            }

            if ($request->bank_account_type_id !== '0' && $request->bank_account_type_id !== 'ALL') {
                $proofList = $proofList->whereHas("employee", function ($query) use ($request) {
                    $query->whereHas("latest_bank_details", function ($q) use ($request) {
                        $q->where("bank_account_type_id", $request->bank_account_type_id);
                    });
                });
            } else if ($request->bank_account_type_id == '0' && $request->bank_account_type_id !== 'ALL') {
                if ($request->type === "PAYROLL") {
                    $proofList = $proofList->where("is_bank", false);
                } else {
                    $proofList = $proofList->whereHas("payroll_list", function ($query) {
                        $query->where("is_bank", false);
                    });
                }
            } 

            if ($request->export === true) {
                // if ($request->bank_account_type_id === 'ALL') {
                //     return $proofList->get();
                // }
                // if ($request->type !== "INCENTIVES") {
                //     $proofList = $proofList->select(['*'])
                //     ->addSelect("net_pay as amount")
                //     ->with([
                //         "timekeeping_branch",
                //         "employee",
                //         "timekeeping_summary"
                //     ]);
                // }
                // $proofList = $proofList->whereHas("payroll_list", function ($query) {
                //     $query->where("is_hold", false);
                // })->get();

                return $proofList->get();
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

            // if ($cutOff && $cutOff->is_cutoff_locked !== 1 && $cutOff->status === "active") {
            //     return $this->sendError(["message" => "You cant generate unfinalized cutoff"], 409);
            // }

            $request["export"] = true;
            $request["converter"] = true;
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

                    if (str_replace(' ', '', $transaction->employee->latest_bank_details->bank_number) == "") {
                        continue;
                    }

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
