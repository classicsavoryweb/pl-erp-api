<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Exports\Compensation\LastPayExport;
use App\Jobs\GenerateLastPayEntry;
use App\Models\AnnualTax;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Incentives;
use App\Models\LastPay;
use App\Models\LastPayDeduction;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use Log;
use App\Http\Services\LastPayService;
use App\Models\ConverterDetail;

class LastPayControllerV2 extends Controller
{
    protected $lastPayService;

    function __construct () {
        $this->lastPayService = new LastPayService();
    }

    public function getAllLastPay(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $lastPays = LastPay::with([
                "employee",
                "employee.current_branch",
                "employee.current_position",
                "deductions",
                "deductions.deduction",
                "employee.government_deductions",
                "employee.government_deductions.deduction",
                "additional_fields.transaction_type",
                "activities" => function ($query) {
                    $query->with("causer")->orderBy("id", "desc");
                }
            ]);

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $lastPays = $lastPays->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $lastPays = $lastPays->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }
                } else {
                    $lastPays = $lastPays->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $lastPays = $lastPays->where("status", $request->status);
            }

            if ($request->has(["branch_id"])) {
                $lastPays = $lastPays->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            $lastPays = $lastPays->orderBy("id", "DESC");

            if ($request->has(["export"]) && $request->export) {
                $lastPays = $lastPays->get();

                return Excel::download(new LastPayExport($lastPays), "last_pay.xls");
            }

            $lastPays = $lastPays->paginate($perPage);

            return $this->sendResponse($lastPays);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllLastPay(Request $request) {
        $request["export"] = true;

        return $this->getAllLastPay($request);
    }

    public function getHoldedEmployees(Request $request) {
        try {
            $employees = Employee::query();
            if ($request->has(["keyWord"])) {
                $keyword = $request->keyWord;

                $employees = $employees->where(function ($query) use ($keyword) {
                    $query->whereAny(
                        ["first_name", "last_name", "middle_name"],
                        'LIKE',
                        "%$keyword%"
                    )->orWhere(function ($query) use ($keyword) {
                            $query->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                        }
                    )->orWhere(function ($query) use ($keyword) {
                            $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                        }
                    );
                });
            }

            $employees = $employees->whereHas("payroll_list", function ($query) {
                $query->where("is_hold", 1);
            })
            ->where("type", "Direct")
            ->get()
            ->take(100);

             return $this->sendResponse($employees);

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getEmployeeInitialLastPayDetails($employeeNumber) {
        try {
            $employee = Employee::with(["payroll_list"])
            ->where("employee_number", $employeeNumber)
            ->first();

            if ($employee) {
                $cutOff = $this->getCutOffForDate($employee->separation_date);
                $year = Carbon::parse($employee->separation_date)->year;
                $payrollPayDate = Cutoff::where("start_date", $cutOff['start_time'])
                    ->value("pay_date");

                if (!$payrollPayDate) {
                    $payrollPayDate = Payroll::where("start_date", $cutOff['start_time'])
                        ->value("pay_date");
                }

                $additionalFields = [];

                $converterDetails = ConverterDetail::where("employee_id", $employee->id)
                ->where("status", "Pending")
                ->get();

                foreach ($converterDetails as $converter) {
                    $additionalFields[] = [
                        "transaction_type_id" => $converter->transaction_type_id,
                        "amount" => $converter->amount
                    ];
                }

                $lastPayDetails = (object) [
                    'year' => $year,
                    'total_payroll' => $this->lastPayService->getAllHoldedPayrollsAmount($employee, $payrollPayDate),
                    'incentives' => $this->lastPayService->getAllHoldedIncentivesAmount($employee, $payrollPayDate),
                    'tax_refund' => $this->lastPayService->getLastPayTaxRefund($employee),
                    'thirteenth_month' => $this->lastPayService->getThirteenthMonthPayAmount($employee),
                    'sl_vl' => 0,
                    'sales' => 0,
                    'meal_allowance' => 0,
                    'additional_fields' => $additionalFields,
                    'remarks' => null,
                    'company_deductions'    => $this->lastPayService->getDeductions($employee, ['Company']),
                    'government_deductions' => $this->lastPayService->getDeductions($employee, ['Pag-ibig', 'SSS']),
                ];

                $lastPay = $this->lastPayService->computeLastPay($employee, $lastPayDetails);
                $lastPay["payrolls"] = $this->lastPayService->getThirteenthMonthPayrollRows($employee);

                return $this->sendResponse($lastPay);
            }

            return $this->sendError(["message" => "Employee not found"]);

        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function postLastPay($lastPayId) {
        try {
            DB::beginTransaction();
            $lastPay = LastPay::with("additional_fields.transaction_type")->find($lastPayId);

            if ($lastPay && $lastPay->status === "Unposted") {
                if ($this->numeric($lastPay->last_pay) < 0) {
                    DB::rollBack();
                    return $this->sendError(["message" => "Negative last pay cannot be posted"]);
                }

                $this->lastPayService->applyPostedLastPayDeductionBalances($lastPay);

                GenerateLastPayEntry::dispatch($lastPay, Auth::user()->id);

                $lastPay->disableLogging();
                $lastPay->update([
                    "status" => "Posted",
                ]);
                $lastPay->enableLogging();

                activity()
                    ->performedOn($lastPay)
                    ->causedBy(Auth::user())
                    ->useLog("Last Pay")
                    ->event("posted")
                    ->log("posted");

                DB::commit();
                return $this->sendResponse(["message" => "Last pay posted successfully"]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Last pay not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getLastPay($employeeNumber) {
        try {
            $employee = Employee::where("employee_number", $employeeNumber)->first(); 

            if ($employee) {
                $lastPay = LastPay::where("employee_id", $employee->id)->first();

                if (!$lastPay) {
                    return $this->sendError(["message" => "Last pay not found"]);
                }

                $companyDeductions = [];
                $lastPay->meal_allowance = $lastPay->meal;
                $lastPayDeductions = $lastPay->deductions()->with(["deduction"])->get();

                foreach ($lastPayDeductions as $deduction) {
                    $employeeDeduction = EmployeeDeduction::where("employee_id", $deduction->employee_id)
                    ->where("reference_doc", $deduction->reference_doc)
                    ->first();

                    if ($employeeDeduction) {
                        $companyDeductions[] = [
                            "deduction_type" => $deduction->deduction->description,
                            "employee_deduction_id" => $employeeDeduction->id,
                            "balance" => $deduction->amount,
                            "amount" => $deduction->amount,
                            "category" => $deduction->deduction->category,
                        ];
                    }
                }

                $lastPay->company_deductions =  $companyDeductions;
                $lastPay["additional_fields"] = $this->lastPayService->getAdditionalFields($employee);
                $lastPay["payrolls"] = $this->lastPayService->getThirteenthMonthPayrollRows($employee);
                $lastPay->government_deductions = $this->lastPayService->getDeductions($employee, ['Pag-ibig', 'SSS'])["deductions"];

                return $this->sendResponse($lastPay);
            }

            return $this->sendError(["message" => "Employee not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function computeLastPay(Request $request, $employeeNumber) {
        try {
            $employee = Employee::with(["payroll_list"])
            ->where("employee_number", $employeeNumber)
            ->first();

            if ($employee) {
                $year = Carbon::parse($employee->separation_date)->year;
                $totalDeductions = 0;

                $companyDeductions = $request->company_deductions ?? [];

                if ($request->reload_deductions) {
                    $companyDeductions = $this->lastPayService->getDeductions($employee, ["Company"])["deductions"];
                }

                foreach ($companyDeductions as $deduction) {
                    $totalDeductions += $deduction["amount"];
                }

                $lastPayDetails = (object) [
                    'year' => $year,
                    'total_payroll' => $request->payroll_hold,
                    'incentives' => $request->incentives,
                    'tax_refund' => $request->tax_refund,
                    'thirteenth_month' => $request->thirteenth_month,
                    'sl_vl' => $request->sl_vl,
                    'sales' => $request->sales,
                    'meal_allowance' => $request->meal_allowance,
                    'remarks' => $request->remarks,
                    'company_deductions'    => [
                        "deductions" => $companyDeductions,
                        "total_deductions" => $totalDeductions
                    ],
                    'government_deductions' => [
                        "deductions" => $request->government_deductions
                    ],
                    'additional_fields' => $request->additional_fields
                ];

                $lastPay = $this->lastPayService->computeLastPay($employee, $lastPayDetails);

                if ($request->save_changes) {
                    $lastPayDetails = LastPay::updateOrCreate([
                        "employee_id" => $employee->id,
                    ], [
                        "payroll_hold" => $lastPay["payroll_hold"],
                        "thirteenth_month" => $lastPay["thirteenth_month"],
                        "year" => $lastPay["year"],
                        "sl_vl" => $lastPay["sl_vl"],
                        "tax_refund" => $lastPay["tax_refund"],
                        "incentives" => $lastPay["incentives"],
                        "total_deductions" => $lastPay["total_deductions"],
                        "sales" => $lastPay["sales"],
                        "meal" => $lastPay["meal_allowance"],
                        "last_pay" => $lastPay["last_pay"],
                        "status" => "Unposted",
                        "remarks" => $lastPay["remarks"],
                    ]);

                    $lastPayDetails->deductions()->delete();
                    $lastPayDetails->additional_fields()->delete();

                    foreach ($lastPay["additional_fields"] as $additional) {
                        $lastPayDetails->additional_fields()->updateOrCreate([
                            "transaction_type_id" => $additional["transaction_type_id"]
                        ],[
                            "amount" => $additional["amount"],
                        ]);
                    }

                    foreach ($lastPay["company_deductions"] as $deduction) {
                        $employeeDeductions = EmployeeDeduction::find($deduction["employee_deduction_id"]);

                        if ($employeeDeductions) {
                            $lastPayDetails->deductions()->updateOrCreate([                            
                                "employee_id" => $employee->id,
                                "reference_doc" => $employeeDeductions->reference_doc,
                                "deduction_id" => $employeeDeductions->deduction_id,
                            ],[
                                "amount" => $deduction["amount"],
                            ]);
                        }
                    }
                }

                return $this->sendResponse($lastPay);
            }
            return $this->sendError(["message" => "Employee not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function updateLastPayRemarks(Request $request, $lastPayId) {
        try {
            $lastPay = LastPay::find($lastPayId);

            if (!$lastPay) {
                return $this->sendError(["message" => "Last pay not found"]);
            }

            $request->validate([
                "remarks" => ["nullable", "string"],
            ]);

            $lastPay->update([
                "remarks" => $request->remarks,
            ]);

            return $this->sendResponse([
                "message" => "Remarks updated successfully",
                "remarks" => $lastPay->remarks,
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function previewLastPay($lastPayId) {
        try {
            $lastPay = LastPay::find($lastPayId);

            if (!$lastPay) {
                return $this->sendError(["message" => "Last pay not found"]);
            }

            return $this->sendResponse($this->lastPayService->buildLastPayPreview($lastPay));
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function taxRefundComputation($lastPayId) {
        try {
            $lastPay = LastPay::find($lastPayId);

            if (!$lastPay) {
                return $this->sendError(["message" => "Last pay not found"]);
            }

            return $this->sendResponse($this->lastPayService->buildTaxRefundComputation($lastPay));
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
