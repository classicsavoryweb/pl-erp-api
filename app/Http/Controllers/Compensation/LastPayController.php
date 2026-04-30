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
    private function numeric($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $value);
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

            $lastPays->getCollection()->transform(function ($lastPay) {
                if ($lastPay->status !== "Unposted") {
                    return $lastPay;
                }

                $companyDeductions = $this->getCurrentCompanyDeductionsForEmployee($lastPay->employee_id);
                $totalDeductions = round($companyDeductions->sum(function ($deduction) {
                    return $this->numeric($deduction->balance ?? $deduction->amount ?? 0);
                }), 2);

                $lastPayAmount = (
                    $this->numeric($lastPay->payroll_hold)
                    + $this->numeric($lastPay->thirteenth_month)
                    + $this->numeric($lastPay->incentives)
                    + $this->numeric($lastPay->sl_vl)
                    + $this->numeric($lastPay->meal)
                    + $this->numeric($lastPay->tax_refund)
                    + $this->numeric($lastPay->sales)
                ) - $totalDeductions;

                $lastPay->setRelation("deductions", $companyDeductions);
                $lastPay->total_deductions = $totalDeductions;
                $lastPay->last_pay = round($lastPayAmount, 2);

                return $lastPay;
            });

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
                if (empty($yearSourceDate)) {
                    return $this->sendError(["message" => "Employee has no separation date."]);
                }
                $cutOff = $this->getCutOffForDate($yearSourceDate);
                $payroll = Payroll::where("start_date", $cutOff['start_time'])->first();
                $payrollPayDate = $payroll?->pay_date;
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

                $payrolls = collect();
                $incentivesPayroll = collect();
                if ($payrollPayDate) {
                    $payrolls = Payroll::where("employee_id", $employee->id)
                        ->where("pay_date", ">=" ,$payrollPayDate)
                        ->get();

                    $incentivesPayroll = Incentives::where("employee_id", $employee->id)
                        ->where("pay_date", ">=" ,$payrollPayDate)
                        ->get();
                }

                foreach ($payrolls as $payroll) {
                    $totalPayroll += $this->numeric($payroll->net_pay);
                }

                foreach ($incentivesPayroll as $incentive) {
                    $incentives += $this->numeric($incentive->incentive_amount);
                }

                $allPayrolls = Payroll::where("employee_id", $employee->id)
                ->where("status", "final")
                ->whereBetween("end_date", [$thirteenthMonthPeriodStart, $thirteenthMonthPeriodEnd])
                ->get();
                $payrollRowsOverride = $this->buildThirteenthMonthPayrollRows($allPayrolls, $yearHolded);

                $annualSalary = 0;
                $annualTaxableIncome = 0;
                $totalWithholdingTax = 0;
                $totalDeductions = 0;

                foreach ($companyDeductions as $deduction) {
                    $totalDeductions += $this->numeric($deduction->balance);
                }

                foreach ($allPayrolls as $payroll) {
                    $salary = $this->numeric($payroll->salary) - ($this->numeric($payroll->undertime_amount) + $this->numeric($payroll->tardiness_amount));
                    $annualTaxableIncome += $this->numeric($payroll->taxable_income);
                    $annualSalary += $salary;
                    $totalWithholdingTax += $this->numeric($payroll->withholding_tax);
                }
                $month13th = $annualSalary / 12;

                $annualTax = AnnualTax::where("minimum_amount", "<=" , $annualTaxableIncome)
                ->where("max_amount", ">=" , $annualTaxableIncome)
                ->first();

                if ($annualTax) {
                    $amountShouldBePaid = $annualTax->tax_lower_limit + (($annualTaxableIncome - $annualTax->minimum_amount) * (0.01 * $annualTax->percentage));
                    $taxRefund = $amountShouldBePaid < $totalWithholdingTax ? $totalWithholdingTax - $amountShouldBePaid : 0;
                } else {
                    $taxRefund = 0;
                }

                if ($lastPayDetails) {
                    $totalPayroll = $this->numeric($lastPayDetails->payroll_hold);
                    $month13th = $this->numeric($lastPayDetails->thirteenth_month);
                    $incentives = $this->numeric($lastPayDetails->incentives);
                    $sl_vl = $this->numeric($lastPayDetails->sl_vl);
                    $meal_allowance = $this->numeric($lastPayDetails->meal);
                    $taxRefund = $this->numeric($lastPayDetails->tax_refund);
                    $sales = $this->numeric($lastPayDetails->sales);
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
                    "payroll_rows_override" => $payrollRowsOverride,
                    "date_holdeed" => $dateHolded
                ]);
            }

            return $this->sendError(["message" => "Employee not found"]);

        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    private function buildThirteenthMonthPayrollRows($payrolls, int $yearHolded): array
    {
        $periods = [];
        $current = Carbon::create($yearHolded - 1, 12, 11)->startOfDay();

        for ($i = 0; $i < 24; $i++) {
            $start = $current->copy();
            if ($start->day === 11) {
                $end = $start->copy()->day(25);
                $current = $start->copy()->day(26);
            } else {
                $end = $start->copy()->addMonth()->day(10);
                $current = $start->copy()->addMonth()->day(11);
            }

            $periods[] = [
                "label" => $start->format("n/j") . " to " . $end->format("j"),
            ];
        }

        $payrollMap = [];
        foreach ($payrolls as $payroll) {
            $startDate = $payroll->start_date ?? null;
            $endDate = $payroll->end_date ?? null;
            if (!$startDate || !$endDate) {
                continue;
            }

            $label = Carbon::parse($startDate)->format("n/j") . " to " . Carbon::parse($endDate)->format("j");
            $salary = $this->numeric($payroll->salary ?? $payroll->net_pay);
            $undertime = $this->numeric($payroll->undertime_amount);
            $tardiness = $this->numeric($payroll->tardiness_amount);
            $amount = $salary - ($undertime + $tardiness);
            $payrollMap[$label] = ($payrollMap[$label] ?? 0) + $amount;
        }

        return array_map(function ($period) use ($payrollMap) {
            $label = $period["label"];
            return [
                "label" => $label,
                "amount" => round($payrollMap[$label] ?? 0, 2),
            ];
        }, $periods);
    }

    private function getCurrentCompanyDeductionsForEmployee($employeeId)
    {
        return EmployeeDeduction::with("deduction")
            ->where("employee_id", $employeeId)
            ->where("balance", ">=", 1)
            ->where("end_loan_status", "!=", "Yes")
            ->whereHas("deduction", function ($query) {
                $query->whereNotIn("category", ["Pag-ibig", "SSS"]);
            })
            ->get()
            ->map(function ($deduction) {
                $deduction->amount = $this->numeric($deduction->balance);
                return $deduction;
            })
            ->values();
    }

    public function createLastPayRecord(Request $request, $employeeNumber) {
        try {
            DB::beginTransaction();

            $request->validate([
                "payroll_hold" => "nullable|numeric|min:0",
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

                $incentives = $this->numeric($request->incentives);
                $sl_vl = $this->numeric($request->sl_vl);
                $meal_allowance = $this->numeric($request->meal_allowance);
                $taxRefund = $this->numeric($request->tax_refund);
                $salesIncentives = $this->numeric($request->sales);

                $yearSourceDate = $employee->separation_date;
                $cutOff = $this->getCutOffForDate($yearSourceDate);
                $payroll = Payroll::where("start_date", $cutOff['start_time'])->first();
                $payrollPayDate = $payroll?->pay_date;
                $source = $yearSourceDate ? Carbon::parse($yearSourceDate) : Carbon::now();
                $yearHolded = (int) $source->year;
                $dec11 = Carbon::create($yearHolded, 12, 11)->startOfDay();
                if ($source->greaterThanOrEqualTo($dec11)) {
                    $yearHolded += 1;
                }
                $thirteenthMonthStartDate = ($yearHolded - 1) . "-12-10";
                $thirteenthMonthPeriodStart = Carbon::create($yearHolded - 1, 12, 11)->format("Y-m-d");
                $thirteenthMonthPeriodEnd = Carbon::create($yearHolded, 12, 10)->format("Y-m-d");

                $payrolls = collect();
                if ($payrollPayDate) {
                    $payrolls = Payroll::where("employee_id", $employee->id)
                        ->where("pay_date", ">=" ,$payrollPayDate)
                        ->get();
                }

                foreach ($payrolls as $payroll) {
                    $totalPayroll += $this->numeric($payroll->net_pay);
                }

                if ($request->has("payroll_hold")) {
                    $totalPayroll = $this->numeric($request->payroll_hold);
                }

                if ($this->numeric($request->incentives) != 0 && $payrollPayDate) {
                    $incentivesPayroll = Incentives::where("employee_id", $employee->id)
                    ->where("pay_date", ">=" ,$payrollPayDate)
                    ->get();
                    
                    foreach ($incentivesPayroll as $incentive) {
                        $incentives += $this->numeric($incentive->incentive_amount);
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
                    $totalDeductions += $this->numeric($deduction->balance);
                }

                foreach ($governmentDeductions as $deduction) {
                    if ($deduction->deduction->description === 'SSS Loans' && $request->sss_loans === 'Yes') {
                        $totalDeductions += $this->numeric($deduction->balance);
                    }
                }

                foreach ($allPayrolls as $payroll) {
                    $salary = $this->numeric($payroll->salary) - ($this->numeric($payroll->undertime_amount) + $this->numeric($payroll->tardiness_amount));
                    $annualTaxableIncome += $this->numeric($payroll->taxable_income);
                    $annualSalary += $salary;
                    $totalWithholdingTax += $this->numeric($payroll->withholding_tax);
                }
                $month13th = $annualSalary / 12;
                if ($request->has("thirteenth_month")) {
                    $month13th = $this->numeric($request->thirteenth_month);
                }

                $annualTax = AnnualTax::where("minimum_amount", "<=" , $annualTaxableIncome)
                ->where("max_amount", ">=" , $annualTaxableIncome)
                ->first();

                if ($annualTax) {
                    $amountShouldBePaid = $this->numeric($annualTax->tax_lower_limit) + (($annualTaxableIncome - $this->numeric($annualTax->minimum_amount)) * (0.01 * $this->numeric($annualTax->percentage)));
                    $taxRefund = $amountShouldBePaid < $totalWithholdingTax ? $totalWithholdingTax - $amountShouldBePaid : 0;
                } else {
                    $taxRefund = 0;
                }

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
                        "amount" => $this->numeric($deduction->balance),
                    ]);
                }

                foreach ($governmentDeductions as $deduction) {
                    if ($deduction->deduction->description === 'SSS Loans' && $request->sss_loans === 'Yes') {
                        $lastPayDetails->deductions()->create([
                            "employee_id" => $employee->id,
                            "reference_doc" => $deduction->reference_doc,
                            "deduction_id" => $deduction->deduction_id,
                            "amount" => $this->numeric($deduction->balance),
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

                $totalDeductions = $this->numeric($lastPay->total_deductions);
                $totalDeductions -= $this->numeric($deductionFromLastPay->amount);
                $lastPayAmount = ($this->numeric($lastPay->payroll_hold) + $this->numeric($lastPay->thirteenth_month) + $this->numeric($lastPay->incentives) + $this->numeric($lastPay->sl_vl) + $this->numeric($lastPay->meal) + $this->numeric($lastPay->tax_refund) + $this->numeric($lastPay->sales)) - $totalDeductions;

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
                                "balance" => $this->numeric($employeeDeduction->balance) + $this->numeric($deductionFromLastPay->amount)
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
                $lastPayAmount = ($this->numeric($lastPay->payroll_hold) + $this->numeric($lastPay->thirteenth_month) + $this->numeric($lastPay->incentives) + $this->numeric($lastPay->sl_vl) + $this->numeric($lastPay->meal) + $this->numeric($lastPay->tax_refund) + $this->numeric($lastPay->sales)) - $this->numeric($allLastPayDeduction);
                $lastPay->update([
                    "total_deductions" => $this->numeric($allLastPayDeduction),
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
                "payroll_hold" => "nullable|numeric|min:0",
                "incentives" => "required",
                "sl_vl" => "required",
                "tax_refund" => "required",
                "sales" => "required",
                "meal_allowance" => "required",
                "thirteenth_month" => "nullable|numeric|min:0"
            ]);

            $lastPay = LastPay::with(["deductions", "deductions.deduction"])->find($lastPayId);

            if ($lastPay) {
                $totalPayroll = $this->numeric($lastPay->payroll_hold);
                $month13th = $this->numeric($lastPay->thirteenth_month);

                if ($request->has("payroll_hold")) {
                    $totalPayroll = $this->numeric($request->payroll_hold);
                }

                $incentives = $this->numeric($request->incentives);
                $sl_vl = $this->numeric($request->sl_vl);
                $meal_allowance = $this->numeric($request->meal_allowance);
                $taxRefund = $this->numeric($request->tax_refund);
                $salesIncentives = $this->numeric($request->sales);
                if ($request->has("thirteenth_month")) {
                    $month13th = $this->numeric($request->thirteenth_month);
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
                                "amount" => $this->numeric($deduction->balance),
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
                                    "balance" => $this->numeric($employeeDeduction->balance) + $this->numeric($deduction->amount)
                                ]);
                            }

                            $deduction->delete();
                        }
                    }
                }

                $totalDeductions = $this->numeric(LastPayDeduction::where("last_pay_id", $lastPay->id)->sum("amount"));

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
