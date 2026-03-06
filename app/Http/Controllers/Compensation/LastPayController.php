<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLastPayEntry;
use App\Models\AnnualTax;
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
use Str;
use Log;

class LastPayController extends Controller
{
    public function getAllLastPay(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $lastPays = LastPay::with([
                "employee",
                "employee.current_branch",
                "deductions",
                "deductions.deduction",
                "employee.government_deductions",
                "employee.government_deductions.deduction",
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

            $lastPays = $lastPays->orderBy("id", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($lastPays);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
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

                $lastPayDetails = LastPay::with(['deductions','deductions.deduction',])
                ->where("employee_id", $employee->id)
                ->first();

                $totalPayroll = 0;
                $month13th = 0;
                $incentives = 0;
                $sl_vl = 0;
                $meal_allowance = 0;
                $taxRefund = 0;
                $sales = 0;

                $companyDeductions = EmployeeDeduction::with("deduction")
                ->where("employee_id", $employee->id)
                ->where("balance", ">=", 1)
                ->where("end_loan_status", "!=", "Yes")
                ->whereHas("deduction", function ($query) {
                    $query->whereNotIn("category", ["Pag-ibig", "SSS"]);
                })
                ->get();

                $governmentDeductions = EmployeeDeduction::with("deduction")
                ->where("employee_id", $employee->id)
                ->where("balance", ">=", 1)
                ->where("end_loan_status", "!=", "Yes")
                ->whereHas("deduction", function ($query) {
                    $query->whereIn("category", ["Pag-ibig", "SSS"]);
                })
                ->get();

                $sssLoans = collect();
                $sssLoansSelected = "No";

                if ($lastPayDetails && $lastPayDetails->deductions) {
                    $sssLoans = $lastPayDetails->deductions->filter(function ($item) {
                        return $item->deduction &&
                            $item->deduction->description === 'SSS Loans';
                    });
                    $sssLoansSelected = $sssLoans->isNotEmpty() ? "Yes" : "No";
                }

                if ($sssLoans->isNotEmpty()) {
                    $sssLoans = $sssLoans->map(function ($loan) use ($employee) {
                        $employeeDeduction = EmployeeDeduction::where("employee_id", $employee->id)
                            ->where("reference_doc", $loan->reference_doc)
                            ->first();

                        $loan->original_amount = $employeeDeduction?->amount;
                        $loan->balance = $loan->amount;
                        if (!is_null($loan->original_amount)) {
                            $loan->amount = $loan->original_amount;
                        }

                        return $loan;
                    });
                }

                if ($sssLoans->isNotEmpty()) {
                    $existingSssRefs = $governmentDeductions
                        ->filter(function ($deduction) {
                            return $deduction->deduction && $deduction->deduction->description === 'SSS Loans';
                        })
                        ->pluck('reference_doc')
                        ->filter()
                        ->values()
                        ->all();

                    $sssForGovernment = $sssLoans->filter(function ($loan) use ($existingSssRefs) {
                        return !in_array($loan->reference_doc, $existingSssRefs);
                    });

                    if ($sssForGovernment->isNotEmpty()) {
                        $governmentDeductions = $governmentDeductions->concat($sssForGovernment);
                    }
                }

                $companyDeductions = $companyDeductions->merge($sssLoans);

                $yearSourceDate = $employee->separation_date;
                $cutOff = $this->getCutOffForDate($yearSourceDate);
                $payroll = Payroll::where("start_date", $cutOff['start_time'])->first();
                $source = $yearSourceDate ? Carbon::parse($yearSourceDate) : Carbon::now();
                $yearHolded = (int) $source->year;
                $dec11 = Carbon::create($yearHolded, 12, 11)->startOfDay();
                if ($source->greaterThanOrEqualTo($dec11)) {
                    $yearHolded += 1;
                }
                $thirteenthMonthStartDate = ($yearHolded - 1) . "-12-10";
                $thirteenthMonthPeriodStart = Carbon::create($yearHolded - 1, 12, 11)->format("Y-m-d");
                $thirteenthMonthPeriodEnd = Carbon::create($yearHolded, 12, 10)->format("Y-m-d");
                $dateHolded = Carbon::parse($employee->separation_date)->format("Y-m-d");

                $payrolls = Payroll::where("employee_id", $employee->id)
                ->where("pay_date", ">=" ,$payroll->pay_date)
                ->get();

                $incentivesPayroll = Incentives::where("employee_id", $employee->id)
                ->where("pay_date", ">=" ,$payroll->pay_date)
                ->get();

                foreach ($payrolls as $payroll) {
                    $totalPayroll += $payroll->net_pay;
                }

                foreach ($incentivesPayroll as $incentive) {
                    $incentives += $incentive->incentive_amount;
                }

                $allPayrolls = Payroll::where("employee_id", $employee->id)
                ->where("status", "final")
                ->whereBetween("end_date", [$thirteenthMonthPeriodStart, $thirteenthMonthPeriodEnd])
                ->get();

                $annualSalary = 0;
                $annualTaxableIncome = 0;
                $totalWithholdingTax = 0;
                $totalDeductions = 0;

                foreach ($companyDeductions as $deduction) {
                    $totalDeductions += $deduction->balance;
                }

                foreach ($allPayrolls as $payroll) {
                    $salary = $payroll->salary - ($payroll->undertime_amount + $payroll->tardiness_amount);
                    $annualTaxableIncome += $payroll->taxable_income;
                    $annualSalary += $salary;
                    $totalWithholdingTax += $payroll->withholding_tax;
                }
                $month13th = $annualSalary / 12;

                $annualTax = AnnualTax::where("minimum_amount", "<=" , $annualTaxableIncome)
                ->where("max_amount", ">=" , $annualTaxableIncome)
                ->first();

                $amountShouldBePaid = $annualTax->tax_lower_limit + (($annualTaxableIncome - $annualTax->minimum_amount) * (0.01 * $annualTax->percentage));
                $taxRefund = $amountShouldBePaid < $totalWithholdingTax ? $totalWithholdingTax - $amountShouldBePaid : 0;

                if ($lastPayDetails) {
                    $totalPayroll = $lastPayDetails->payroll_hold;
                    $month13th = $lastPayDetails->thirteenth_month;
                    $incentives = $lastPayDetails->incentives; 
                    $sl_vl = $lastPayDetails->sl_vl;
                    $meal_allowance = $lastPayDetails->meal;
                    $taxRefund = $lastPayDetails->tax_refund;
                    $sales = $lastPayDetails->sales;
                    $totalDeductions = $lastPayDetails->total_deductions;
                }

                $lastPayAmount = ($totalPayroll + $month13th + $incentives + $sl_vl + $meal_allowance + $taxRefund + $sales)- $totalDeductions;

                return $this->sendResponse([
                    "company_deductions" => $companyDeductions,
                    "government_deductions" => $governmentDeductions,
                    "payroll_hold" => $totalPayroll,
                    "thirteenth_month" => round($month13th, 2),
                    "sales" => $sales,
                    "incentives" => $incentives,
                    "sl_vl" => $sl_vl,
                    "meal_allowance" => $meal_allowance,
                    "year" => $yearHolded,
                    "tax_refund" => $taxRefund,
                    "total_deductions" => $totalDeductions,
                    "last_pay" => round($lastPayAmount, 2),
                    "sss_loans" => $sssLoansSelected,
                    "payrolls" => $payrolls,
                    "date_holdeed" => $dateHolded
                ]);
            }

            return $this->sendError(["message" => "Employee not found"]);

        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createLastPayRecord(Request $request, $employeeNumber) {
        try {
            DB::beginTransaction();

            $request->validate([
                "incentives" => "required",
                "sl_vl" => "required",
                "tax_refund" => "required",
                "sales" => "required",
                "meal_allowance" => "required",
                "thirteenth_month" => "nullable|numeric|min:0"
            ]);
            $employee = Employee::with(["payroll_list"])
            ->where("employee_number", $employeeNumber)
            ->first();

            if ($employee) {
                $existingLastPay = LastPay::where("employee_id", $employee->id)->exists();
                if ($existingLastPay) {
                    DB::rollBack();
                    return $this->sendError(["message" => "Last pay record already exists"]);
                }

                $companyDeductions = EmployeeDeduction::with("deduction")
                ->where("employee_id", $employee->id)
                ->where("balance", ">=", 1)
                ->where("end_loan_status", "!=", "Yes")
                ->whereHas("deduction", function ($query) {
                    $query->whereNotIn("category", ["Pag-ibig", "SSS"]);
                })
                ->get();

                $governmentDeductions = EmployeeDeduction::with("deduction")
                ->where("employee_id", $employee->id)
                ->where("balance", ">=", 1)
                ->where("end_loan_status", "!=", "Yes")
                ->whereHas("deduction", function ($query) {
                    $query->whereIn("category", ["Pag-ibig", "SSS"]);
                })
                ->get();

                $totalPayroll = 0;
                $month13th = 0;

                $incentives = $request->incentives ?? 0;
                $sl_vl = $request->sl_vl ?? 0;
                $meal_allowance = $request->meal_allowance ?? 0;
                $taxRefund = $request->tax_refund ?? 0;
                $salesIncentives = $request->sales ?? 0;

                $yearSourceDate = $employee->separation_date;
                $cutOff = $this->getCutOffForDate($yearSourceDate);
                $payroll = Payroll::where("start_date", $cutOff['start_time'])->first();
                $source = $yearSourceDate ? Carbon::parse($yearSourceDate) : Carbon::now();
                $yearHolded = (int) $source->year;
                $dec11 = Carbon::create($yearHolded, 12, 11)->startOfDay();
                if ($source->greaterThanOrEqualTo($dec11)) {
                    $yearHolded += 1;
                }
                $thirteenthMonthStartDate = ($yearHolded - 1) . "-12-10";
                $thirteenthMonthPeriodStart = Carbon::create($yearHolded - 1, 12, 11)->format("Y-m-d");
                $thirteenthMonthPeriodEnd = Carbon::create($yearHolded, 12, 10)->format("Y-m-d");

                $payrolls = Payroll::where("employee_id", $employee->id)
                ->where("pay_date", ">=" ,$payroll->pay_date)
                ->get();

                foreach ($payrolls as $payroll) {
                    $totalPayroll += $payroll->net_pay;
                }

                if ($request->incentives != 0) {
                    $incentivesPayroll = Incentives::where("employee_id", $employee->id)
                    ->where("pay_date", ">=" ,$payroll->pay_date)
                    ->get();
                    
                    foreach ($incentivesPayroll as $incentive) {
                        $incentives += $incentive->incentive_amount;
                    }
                }

                $allPayrolls = Payroll::where("employee_id", $employee->id)
                ->where("status", "final")
                ->whereBetween("end_date", [$thirteenthMonthPeriodStart, $thirteenthMonthPeriodEnd])
                ->get();

                $annualSalary = 0;
                $annualTaxableIncome = 0;
                $totalWithholdingTax = 0;
                $totalDeductions = 0;

                foreach ($companyDeductions as $deduction) {
                    $totalDeductions += $deduction->balance;
                }

                foreach ($governmentDeductions as $deduction) {
                    if ($deduction->deduction->description === 'SSS Loans' && $request->sss_loans === 'Yes') {
                        $totalDeductions += $deduction->balance;
                    }
                }

                foreach ($allPayrolls as $payroll) {
                    $salary = $payroll->salary - ($payroll->undertime_amount + $payroll->tardiness_amount);
                    $annualTaxableIncome += $payroll->taxable_income;
                    $annualSalary += $salary;
                    $totalWithholdingTax += $payroll->withholding_tax;
                }
                $month13th = $annualSalary / 12;
                if ($request->has("thirteenth_month")) {
                    $month13th = (float) $request->thirteenth_month;
                }

                $annualTax = AnnualTax::where("minimum_amount", "<=" , $annualTaxableIncome)
                ->where("max_amount", ">=" , $annualTaxableIncome)
                ->first();

                $amountShouldBePaid = $annualTax->tax_lower_limit + (($annualTaxableIncome - $annualTax->minimum_amount) * (0.01 * $annualTax->percentage));
                $taxRefund = $amountShouldBePaid < $totalWithholdingTax ? $totalWithholdingTax - $amountShouldBePaid : 0;

                $lastPay = ($totalPayroll + $month13th + $incentives + $sl_vl + $meal_allowance + $taxRefund + $salesIncentives)- $totalDeductions;

                $lastPayDetails = LastPay::create([
                    "employee_id" => $employee->id,
                    "payroll_hold" => $totalPayroll,
                    "thirteenth_month" => $month13th,
                    "year" => $yearHolded,
                    "sl_vl" => $sl_vl,
                    "tax_refund" => $taxRefund,
                    "incentives" => $incentives,
                    "total_deductions" => $totalDeductions,
                    "sales" => $salesIncentives,
                    "meal" => $meal_allowance,
                    "last_pay" => $lastPay,
                    "status" => "Unposted",
                ]);

                foreach ($companyDeductions as $deduction) {
                    $lastPayDetails->deductions()->create([
                        "employee_id" => $employee->id,
                        "reference_doc" => $deduction->reference_doc,
                        "deduction_id" => $deduction->deduction_id,
                        "amount" => $deduction->balance,
                    ]);
                }

                foreach ($governmentDeductions as $deduction) {
                    if ($deduction->deduction->description === 'SSS Loans' && $request->sss_loans === 'Yes') {
                        $lastPayDetails->deductions()->create([
                            "employee_id" => $employee->id,
                            "reference_doc" => $deduction->reference_doc,
                            "deduction_id" => $deduction->deduction_id,
                            "amount" => $deduction->balance,
                        ]);
                    }
                }

                foreach ($governmentDeductions as $deduction) {
                    if ($deduction->deduction->description === 'SSS Loans' && $request->sss_loans === 'Yes') {
                        $deduction->update([
                            "balance" => 0
                        ]);
                    }
                }

                DB::commit();
                return $this->sendResponse([
                    "company_deductions" => $companyDeductions,
                    "government_deductions" => $governmentDeductions,
                    "payroll_hold" => $totalPayroll,
                    "thirteenth_month" => round($month13th, 2),
                    "sales" => 0,
                    "incentives" => $incentives,
                    "sl_vl" => $sl_vl,
                    "meal_allowance" => $meal_allowance,
                    "year" => $yearHolded,
                    "tax_refund" => $taxRefund,
                    "total_deductions" => $totalDeductions,
                    "last_pay" => round($lastPay, 2),
                    "message" => "Successfully created"
                ]);
            }
            DB::rollBack();
            return $this->sendError(["message" => "Employee not found"]);

        } catch(Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function removeLastPayDeduction(Request $request, $employeeNumber) {
        try {
            DB::beginTransaction();

            $request->validate([
                "reference_doc" => "required"
            ]);

            $employee = Employee::where('employee_number', $employeeNumber)->first();

            $deductionFromLastPay = LastPayDeduction::where('reference_doc', $request->reference_doc)
                ->where('employee_id', $employee->id)
                ->first();

            if ($deductionFromLastPay) {
                $lastPay = LastPay::where('employee_id', $employee->id)->first();

                $totalDeductions = $lastPay->total_deductions;
                $totalDeductions -= $deductionFromLastPay->amount;
                $lastPayAmount = ($lastPay->payroll_hold + $lastPay->thirteenth_month + $lastPay->incentives + $lastPay->sl_vl + $lastPay->meal + $lastPay->tax_refund + $lastPay->sales) - $totalDeductions;

                $lastPay->update([
                    "total_deductions" => $totalDeductions,
                    "last_pay" => $lastPayAmount
                ]);

                if ($deductionFromLastPay) {
                    $deductionType = $deductionFromLastPay->deduction->description ?? null;
                    if ($deductionType === "SSS Loans") {
                        $employeeDeduction = EmployeeDeduction::where("employee_id", $employee->id)
                            ->where("reference_doc", $deductionFromLastPay->reference_doc)
                            ->first();

                        if ($employeeDeduction) {
                            $employeeDeduction->update([
                                "balance" => $employeeDeduction->balance + $deductionFromLastPay->amount
                            ]);
                        }
                    }

                    $deductionFromLastPay->delete();
                }

                DB::commit();
                return $this->sendResponse([
                    "message" => "Deduction updated successfully",
                ]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Last Pay Deduction not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editLastPayDeductionDetails(Request $request, $lastPayDeductionId) {
        try {
            DB::beginTransaction();

            $request->validate([
                "amount" => "required",
            ]);

            $lastPayDeduction = LastPayDeduction::find($lastPayDeductionId);

            if ($lastPayDeduction) {
                $lastPayDeduction->update([
                    "amount" => $request->amount
                ]);

                $allLastPayDeduction = LastPayDeduction::where('last_pay_id',$lastPayDeduction->last_pay_id)
                    ->sum('amount');

                $lastPay = LastPay::where('id', $lastPayDeduction->last_pay_id)->first();
                $lastPayAmount = ($lastPay->payroll_hold + $lastPay->thirteenth_month + $lastPay->incentives + $lastPay->sl_vl + $lastPay->meal + $lastPay->tax_refund + $lastPay->sales) - $allLastPayDeduction;
                $lastPay->update([
                    "total_deductions" => $allLastPayDeduction,
                    "last_pay" => $lastPayAmount
                ]);

                DB::commit();
                return $this->sendResponse([
                    "message" => "Last Pay Deduction updated successfully"
                ]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Last Pay Deduction not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function postLastPay($lastPayId) {
        try {
            DB::beginTransaction();
            $lastPay = LastPay::find($lastPayId);

            if ($lastPay && $lastPay->status === "Unposted") {

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
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editLastPayRecord(Request $request, $lastPayId) {
        try {
            DB::beginTransaction();
            $request->validate([
                "incentives" => "required",
                "sl_vl" => "required",
                "tax_refund" => "required",
                "sales" => "required",
                "meal_allowance" => "required",
                "thirteenth_month" => "nullable|numeric|min:0"
            ]);

            $lastPay = LastPay::with(["deductions", "deductions.deduction"])->find($lastPayId);

            if ($lastPay) {
                $totalPayroll = $lastPay->payroll_hold;
                $month13th = $lastPay->thirteenth_month;

                $incentives = $request->incentives ?? 0;
                $sl_vl = $request->sl_vl ?? 0;
                $meal_allowance = $request->meal_allowance ?? 0;
                $taxRefund = $request->tax_refund ?? 0;
                $salesIncentives = $request->sales ?? 0;
                if ($request->has("thirteenth_month")) {
                    $month13th = (float) $request->thirteenth_month;
                }

                $includeSssLoans = $request->sss_loans === "Yes";
                $currentDeductions = $lastPay->deductions ?? collect();
                $sssDeductions = $currentDeductions->filter(function ($item) {
                    return $item->deduction && $item->deduction->description === 'SSS Loans';
                });

                if ($includeSssLoans) {
                    if ($sssDeductions->isEmpty()) {
                        $employeeDeductions = EmployeeDeduction::with("deduction")
                        ->where("employee_id", $lastPay->employee_id)
                        ->where("balance", ">=", 1)
                        ->where("end_loan_status", "!=", "Yes")
                        ->whereHas("deduction", function ($query) {
                            $query->where("description", "SSS Loans");
                        })
                        ->get();

                        foreach ($employeeDeductions as $deduction) {
                            $lastPay->deductions()->create([
                                "employee_id" => $lastPay->employee_id,
                                "reference_doc" => $deduction->reference_doc,
                                "deduction_id" => $deduction->deduction_id,
                                "amount" => $deduction->balance,
                            ]);

                            $deduction->update([
                                "balance" => 0
                            ]);
                        }
                    }
                } else {
                    if ($sssDeductions->isNotEmpty()) {
                        foreach ($sssDeductions as $deduction) {
                            $employeeDeduction = EmployeeDeduction::where("employee_id", $lastPay->employee_id)
                                ->where("reference_doc", $deduction->reference_doc)
                                ->first();

                            if ($employeeDeduction) {
                                $employeeDeduction->update([
                                    "balance" => $employeeDeduction->balance + $deduction->amount
                                ]);
                            }

                            $deduction->delete();
                        }
                    }
                }

                $totalDeductions = LastPayDeduction::where("last_pay_id", $lastPay->id)->sum("amount");

                $lastPayAmount = ($totalPayroll + $month13th + $incentives + $sl_vl + $meal_allowance + $taxRefund + $salesIncentives)- $totalDeductions;

                $lastPay->update([
                    "payroll_hold" => $totalPayroll,
                    "thirteenth_month" => $month13th,
                    "sl_vl" => $sl_vl,
                    "tax_refund" => $taxRefund,
                    "incentives" => $incentives,
                    "total_deductions" => $totalDeductions,
                    "sales" => $salesIncentives,
                    "meal" => $meal_allowance,
                    "last_pay" => $lastPayAmount
                ]);
                DB::commit();
                return $this->sendResponse([
                    "payroll_hold" => $totalPayroll,
                    "thirteenth_month" => round($month13th, 2),
                    "sales" => 0,
                    "incentives" => $incentives,
                    "sl_vl" => $sl_vl,
                    "meal_allowance" => $meal_allowance,
                    "tax_refund" => $taxRefund,
                    "year" => $lastPay->year,
                    "total_deductions" => $totalDeductions,
                    "last_pay" => round($lastPayAmount, 2),
                    "sss_loans" => $includeSssLoans ? "Yes" : "No",
                    "message" => "Last pay updated successfully"
                ]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Last pay not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
