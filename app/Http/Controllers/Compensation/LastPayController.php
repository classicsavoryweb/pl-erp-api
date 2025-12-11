<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\AnnualTax;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Incentives;
use App\Models\LastPay;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use Str;

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
                "employee.government_deductions.deduction"
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

                $lastPayDetails = LastPay::where("employee_id", $employee->id)->first();

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

                $yearHolded = Carbon::parse($employee->payroll_list->holded_at)->year;
                $dateHolded = Carbon::parse($employee->payroll_list->holded_at)->format("Y-m-d");

                $payrolls = Payroll::where("employee_id", $employee->id)
                ->where("pay_date", ">=" ,$dateHolded)
                ->get();

                $incentivesPayroll = Incentives::where("employee_id", $employee->id)
                ->where("pay_date", ">=" ,$dateHolded)
                ->get();

                foreach ($payrolls as $payroll) {
                    $totalPayroll += $payroll->net_pay;
                }

                foreach ($incentivesPayroll as $incentive) {
                    $incentives += $incentive->incentive_amount;
                }

                $allPayrolls = Payroll::whereYear("pay_date", $yearHolded)
                ->where("employee_id", $employee->id)
                ->where("status", "final")
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
                    "last_pay" => round($lastPayAmount, 2)
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
                "meal_allowance" => "required"
            ]);

            $employee = Employee::with(["payroll_list"])
            ->where("employee_number", $employeeNumber)
            ->first();

            if ($employee) {
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

                $yearHolded = Carbon::parse($employee->payroll_list->holded_at)->year;
                $dateHolded = Carbon::parse($employee->payroll_list->holded_at)->format("Y-m-d");

                $payrolls = Payroll::where("employee_id", $employee->id)
                ->where("pay_date", ">=" ,$dateHolded)
                ->get();

                $incentivesPayroll = Incentives::where("employee_id", $employee->id)
                ->where("pay_date", ">=" ,$dateHolded)
                ->get();

                foreach ($payrolls as $payroll) {
                    $totalPayroll += $payroll->net_pay;
                }

                foreach ($incentivesPayroll as $incentive) {
                    $incentives += $incentive->incentive_amount;
                }

                $allPayrolls = Payroll::whereYear("pay_date", $yearHolded)
                ->where("employee_id", $employee->id)
                ->where("status", "final")
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
                    "status" => "Unposted"
                ]);

                foreach ($companyDeductions as $deduction) {
                    $lastPayDetails->deductions()->create([
                        "employee_id" => $employee->id,
                        "reference_doc" => $deduction->reference_doc,
                        "deduction_id" => $deduction->deduction_id,
                        "amount" => $deduction->balance,
                    ]);
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

    public function postLastPay($lastPayId) {
        try {
            DB::beginTransaction();
            $lastPay = LastPay::find($lastPayId);

            if ($lastPay && $lastPay->status === "Unposted") {
                $lastPay->update(["status" => "Posted"]);

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
                "meal_allowance" => "required"
            ]);

            $lastPay = LastPay::find($lastPayId);

            if ($lastPay) {
                $totalPayroll = $lastPay->payroll_hold;
                $month13th = $lastPay->thirteenth_month;

                $incentives = $request->incentives ?? 0;
                $sl_vl = $request->sl_vl ?? 0;
                $meal_allowance = $request->meal_allowance ?? 0;
                $taxRefund = $request->tax_refund ?? 0;
                $salesIncentives = $request->sales ?? 0;

                $totalDeductions = $lastPay->total_deductions;

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
                    "last_pay" => $lastPayAmount,
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
