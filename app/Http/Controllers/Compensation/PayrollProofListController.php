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
                "bank_account_type_id" => "required",
                "branch_id" => "nullable"
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

            $proofList = $proofList->with(["employee", "employee.payroll_list"])
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
                ->whereHas("payroll_list", function ($query) {
                    $query->where("is_included", 1)
                    ->where("is_bank", 1)
                    ->where("is_hold", 0);
                })
                ->orderBy(
                    Employee::select("last_name")
                        ->whereColumn("employees.id", "incentives.employee_id")
                );

                if ($request->filled("branch_id")) {
                    $proofList = $proofList->where("branch_id", $request->branch_id);
                }
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

                if ($request->filled("branch_id")) {
                    $proofList = $proofList->where(function ($query) use ($request) {
                        $query->where("p.timekeeping_branch_id", $request->branch_id)
                            ->orWhere(function ($subQuery) use ($request) {
                                $subQuery->whereNull("p.id")
                                    ->whereHas("employee", function ($employeeQuery) use ($request) {
                                        $employeeQuery->where("branch_id", $request->branch_id);
                                    });
                            });
                    });
                }
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

                if ($request->filled("branch_id")) {
                    $proofList = $proofList->where(function ($query) use ($request) {
                        $query->where("p.timekeeping_branch_id", $request->branch_id)
                            ->orWhere(function ($subQuery) use ($request) {
                                $subQuery->whereNull("p.id")
                                    ->whereHas("employee", function ($employeeQuery) use ($request) {
                                        $employeeQuery->where("branch_id", $request->branch_id);
                                    });
                            });
                    });
                }
            } else if ($request->type === "PAYROLL" && $request->export && $request->converter) {
                $proofList = $proofList->select([
                    "status",
                    "id",
                    "employee_id",
                    "net_pay as amount",
                    "cut_off", 
                ])->where("net_pay", ">", 0)
                ->whereHas("payroll_list", function ($query) {
                    $query->where("is_hold", false);
                })->orderBy(
                    Employee::select("last_name")
                        ->whereColumn("employees.id", "payroll.employee_id")
                );

                if ($request->filled("branch_id")) {
                    $proofList = $proofList->where("timekeeping_branch_id", $request->branch_id);
                }
            }

            if ($request->bank_account_type_id !== '0' && $request->bank_account_type_id !== 'ALL') {
                $proofList = $proofList->whereHas("employee", function ($query) use ($request) {
                    $query->whereHas("latest_bank_details", function ($q) use ($request) {
                        $q->where("bank_account_type_id", $request->bank_account_type_id);
                    })->whereHas("payroll_list", function ($query) {
                        $query->where("is_bank", true);
                    });
                });
            
            } else if ($request->bank_account_type_id == '0' && $request->bank_account_type_id !== 'ALL') {
                if ($request->type === "PAYROLL") {
                    $proofList = $proofList->where("is_bank", false)
                    ->where("is_hold", false);
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
            $request["converter"] = true;
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
            if ($sheetXml === false) {
                $zip->close();
                @unlink($tempCopyPath);
                return $this->sendError(["message" => "Invalid converter template: missing Sheet1."]);
            }

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->loadXML($sheetXml);
            $sheetData = $dom->getElementsByTagName('sheetData')->item(0);
            if (!$sheetData) {
                $zip->close();
                @unlink($tempCopyPath);
                return $this->sendError(["message" => "Invalid converter template: missing sheet data."]);
            }

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
                $bankNumber = (string) data_get($transaction, 'employee.latest_bank_details.bank_number', '');
                $bankNumber = str_replace(' ', '', $bankNumber);

                if ($bankNumber === '') {
                    continue;
                }

                $fullName = (string) data_get($transaction, 'employee.full_name', '');
                if ($fullName === '') {
                    continue;
                }

                $colA = "A".$rowIndex;
                $colB = "B".$rowIndex;
                $colC = "C".$rowIndex;

                $this->writeCell($dom, $sheetData, $colA, $bankNumber);
                $this->writeCell($dom, $sheetData, $colB, (string) round((float) data_get($transaction, 'amount', 0), 2));
                $this->writeCell($dom, $sheetData, $colC, $fullName);

                $rowIndex++;
                $writtenRows++;
            }

            if ($writtenRows === 0) {
                $zip->close();
                @unlink($tempCopyPath);
                return $this->sendError(["message" => "No valid prooflist rows with bank account numbers found for converter export."]);
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
