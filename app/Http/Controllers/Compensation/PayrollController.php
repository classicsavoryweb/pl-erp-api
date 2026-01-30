<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Http\Services\PayrollService;
use App\Models\Incentives;
use App\Models\Payroll;
use App\Models\Tardiness;
use App\Models\Tax;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Str;
use Log;

class PayrollController extends Controller
{
    public function getAllPayroll(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;
            $currentCutOff = $this->getCurrentCutoff();
            $year = $request->year ?? Carbon::now()->year;

            // $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
            //     ? $year . '-' . $request->cutoff["start_time"]
            //     : $currentCutOff['start_time'];

            // $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
            //     ? $year . '-' . $request->cutoff["end_time"]
            //     : $currentCutOff['end_time'];

            $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $request->year);
            $start_time = $dates["start_date"] ?? $currentCutOff['start_time'];
            $end_time = $dates["end_date"] ?? $currentCutOff['end_time'];

            $payroll = Payroll::with(["employee","employee.current_branch", "employee.current_position", "timekeeping_summary",])
            ->where("cut_off", $start_time."-".$end_time);

            if ($request->has(["branch_id"])) {
                $payroll = $payroll->whereHas("employee", function ($query) use ($request) {
                    return $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $payroll = $payroll->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $payroll = $payroll->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $payroll = $payroll->where($column, 'LIKE', "%$keyword%");
                }
            }

            // if ($request->has(["export"]) && $request->export) {
            //     $payroll = $payroll->get();
            //     return Excel::download(new EmployeeDeductionExport($payroll), "payroll.xls");
            // }

            $payroll = $payroll->paginate($perPage);

            return $this->sendResponse($payroll);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getPayrollDetails ($id) {
        try {
            $payroll = Payroll::with([
                "employee",
                "employee.current_branch",
                "employee.current_position",
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($id);

            if ($payroll) {
                $employee = $payroll->employee;

                $startDate = Carbon::parse($payroll->start_date)->subMonths(1)->day(26)->format("Y-m-d");
                $endDate = Carbon::parse($payroll->start_date)->day(25)->format("Y-m-d");

                $firstCutOff = $this->getCutOffForDate($startDate);
                $secondCutOff = $this->getCutOffForDate($endDate);

                $firstTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
                ->where("cut_off", $firstCutOff["start_time"]."-".$firstCutOff["end_time"])
                ->first();

                $secondTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
                ->where("cut_off", $secondCutOff["start_time"]."-".$secondCutOff["end_time"])
                ->first();

                $firstCutOffDays = $firstTimeKeeping->regular_days ?? 0;
                $secondCutOffDays = $secondTimeKeeping->regular_days ?? 0;

                $days = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13;

                $firstCutOffWorkDays = $employee->getCutOffWorkDays($firstCutOff);
                $secondCutOffWorkDays = $employee->getCutOffWorkDays($secondCutOff);

                $monthShouldBeWorkDays = ($firstCutOffWorkDays > 0 ? $firstCutOffWorkDays : $days)
                + ($secondCutOffWorkDays > 0 ? $secondCutOffWorkDays : $days);

                $monthWorkedDays = $firstCutOffDays + $secondCutOffDays;

                $year = Carbon::parse($payroll->pay_date)->year;

                $tardiness = TimeKeeping::with(["payment"])
                ->where("employee_id", $payroll->employee_id)
                ->whereBetween("date", [$payroll->start_date, $payroll->end_date])
                ->where("tardiness", ">" ,0)
                ->get();

                $undertime = TimeKeeping::with(["payment"])
                ->where("employee_id", $payroll->employee_id)
                ->whereBetween("date", [$payroll->start_date, $payroll->end_date])
                ->where("undertime", ">", 0)
                ->get();

                $incentives = Incentives::where("employee_id", $payroll->employee_id)
                ->where("pay_date", $payroll->pay_date)
                ->first();

                $tardinessOffenses = Tardiness::with(["tardiness_sanction"])
                ->where("employee_id", $payroll->employee_id)
                ->where("status", "Approved")
                ->whereIn("year", [$year - 1, $year])
                ->orderBy("id", "DESC")
                ->get();

                $payroll["tardiness_list"] = $tardiness;
                $payroll["undertime_list"] = $undertime;
                $payroll["incentives"] = $incentives;
                $payroll["tardiness_offenses"] = $tardinessOffenses;
                $payroll["worked_days"] = $monthWorkedDays;
                $payroll["days"] = $monthShouldBeWorkDays;

                return $this->sendResponse($payroll);
            }

            return $this->sendError(["message" => "Payroll not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function updateCompanyDeductions(Request $request, $payrollId) {
        try {
            DB::beginTransaction();

            $request->validate([
                "deduction_id" => "required"
            ]);

            $payroll = Payroll::with(["employee",
                "employee.current_branch",
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($payrollId);

            if ($payroll) {
                $deductions = json_decode($payroll->deductions, true);
                $newDeductions = [];
                $totalDeductions = $payroll->total_deductions;
                $amountToBeAdded = 0;

                foreach ($deductions as $deduction) {
                    if ($deduction["deduction_id"] != $request->deduction_id) {
                        $newDeductions[] = $deduction;
                    } else {
                        $totalDeductions -= $deduction["deducted_amount"];
                        $amountToBeAdded += $deduction["deducted_amount"];
                    }
                }

                $netPay = $payroll->net_pay + $amountToBeAdded;

                $payroll->update([
                    "net_pay" => $netPay,
                    "deductions" => json_encode($newDeductions),
                    "total_deductions" => $totalDeductions
                ]);

                DB::commit();
                return $this->sendResponse([
                    "message" => "Deduction updated successfully",
                    "details" => $payroll
                ]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Payroll not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editCompanyDeductions(Request $request, $payrollId) {
        try {
            DB::beginTransaction();

            $request->validate([
                "deduction_id" => "required",
                "deducted_amount" => "required",
            ]);

            $payroll = Payroll::with(["employee",
                "employee.current_branch",
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($payrollId);

            if ($payroll) {
                $deductions = json_decode($payroll->deductions, true);
                $newDeductions = [];
                $totalDeductions = $payroll->total_deductions;
                $amountToBeAdded = 0;
                $amountToDeduct = 0;

                foreach ($deductions as $deduction) {
                    if ($deduction["deduction_id"] != $request->deduction_id) {
                        $newDeductions[] = $deduction;
                    } else {
                        if ($request->deducted_amount > $deduction["deducted_amount"]) {
                            $amountToDeduct = $request->deducted_amount - $deduction["deducted_amount"];
                        } else {
                            $amountToBeAdded = $deduction["deducted_amount"] - $request->deducted_amount;
                        }

                        $totalDeductions += $request->deducted_amount - $deduction["deducted_amount"];
                        $newDeductions[] = [
                            "deduction_id" => $deduction["deduction_id"],
                            "type" => $deduction["type"],
                            "monthly_amortization" => $deduction["monthly_amortization"],
                            "balance" => $deduction["balance"],
                            "deducted_amount" => $request->deducted_amount
                        ];
                    }
                }

                if ($totalDeductions > $payroll->net_pay) {
                    return $this->sendError(["message" => "Net pay cannot be a negative amount."]);
                }

                $netPay = ($payroll->net_pay + $amountToBeAdded) - $amountToDeduct;

                $payroll->update([
                    "net_pay" => $netPay,
                    "deductions" => json_encode($newDeductions),
                    "total_deductions" => $totalDeductions
                ]);

                DB::commit();
                return $this->sendResponse([
                    "message" => "Deduction updated successfully",
                    "details" => $payroll
                ]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Payroll not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function updatePayrollIncentive(Request $request, $payrollId) {
        try {
            DB::beginTransaction();

            $payroll = Payroll::with(["employee",
                "employee.current_branch",
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($payrollId);

            if ($payroll) {

                $employee = $payroll->employee;

                $additional_incentive = $request->additional_incentive;
                $remarks = $request->remarks;

                $incentives = Incentives::where("employee_id", $payroll->employee_id)
                ->where("pay_date", $payroll->pay_date)
                ->first();

                $amount_rate = $incentives->amount_rate;
                $adjustment_days = $incentives->adjustment_days;
                $adjustment_percent = $incentives->adjustment_percent;

                $startDate = Carbon::parse($payroll->start_date)->subMonths(1)->day(26)->format("Y-m-d");
                $endDate = Carbon::parse($payroll->start_date)->day(25)->format("Y-m-d");

                $firstCutOff = $this->getCutOffForDate($startDate);
                $secondCutOff = $this->getCutOffForDate($endDate);

                $firstTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
                ->where("cut_off", $firstCutOff["start_time"]."-".$firstCutOff["end_time"])
                ->first();

                $secondTimeKeeping = TimeKeepingSummary::where("employee_id", $employee->id)
                ->where("cut_off", $secondCutOff["start_time"]."-".$secondCutOff["end_time"])
                ->first();

                $firstCutOffDays = $firstTimeKeeping->regular_days ?? 0;
                $secondCutOffDays = $secondTimeKeeping->regular_days ?? 0;

                $days = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13;

                $firstCutOffWorkDays = $employee->getCutOffWorkDays($firstCutOff);
                $secondCutOffWorkDays = $employee->getCutOffWorkDays($secondCutOff);

                $monthShouldBeWorkDays = ($firstCutOffWorkDays > 0 ? $firstCutOffWorkDays : $days)
                + ($secondCutOffWorkDays > 0 ? $secondCutOffWorkDays : $days);

                $monthWorkedDays = $firstCutOffDays + $secondCutOffDays;

                $final_incentives_amount = ($amount_rate + $additional_incentive) - (($amount_rate) 
                * ($adjustment_percent / 100)) - (($amount_rate) 
                / $monthShouldBeWorkDays * ($adjustment_days));

                $incentives->update([
                    "incentive_amount" => $final_incentives_amount,
                    "adjustment_days" => $adjustment_days,
                    "adjustment_percent" => $adjustment_percent,
                    "additional_incentive" => $additional_incentive,
                    "remarks" => $remarks,
                ]);

                DB::commit();
                return $this->sendResponse([
                    "message" => "Incentive updated successfully",
                    "incentives" => $incentives,
                    "days" => $monthShouldBeWorkDays,
                    "worked_days" => $monthWorkedDays
                ]);
            } else {
                return $this->sendError(["message" => "Payroll not found"]);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function updatePayrollDetails (Request $request, $payrollId) {
        try {
            DB::beginTransaction();

            $payroll = Payroll::with([
                "employee",
                "employee.current_branch",
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($payrollId);

            if ($payroll) {
                $payrollService = new PayrollService();
                $remarks = $request->remarks ?? null;
                $manual_deductions = $request->manual_deductions ?? 0;
                $non_taxable_adjustments = $request->non_taxable_adjustment ?? 0;
                $taxable_adjustment = $request->taxable_adjustment ?? 0;

                $newNetPay = $payroll->net_pay;

                $amountToDeduct = 0;
                $taxableIncome = $payroll->taxable_income;
                $withHoldingTax = $payroll->withholding_tax;
                $totalDeductions = $payroll->total_deductions;
                $additionalWorkingHoursTotal = $payroll->additional_working_hours_total;

                if ($manual_deductions != $payroll->manual_deductions) {
                    $amountToDeduct = $manual_deductions - $payroll->manual_deductions;
                    $newNetPay -= $amountToDeduct;
                }

                if ($non_taxable_adjustments != $payroll->non_taxable_adjustment) {
                    $newNetPay += $non_taxable_adjustments - $payroll->non_taxable_adjustment;
                    $remarks = "NON TAXABLE ADJUSTMENT MANUALLY ENCODED: ". $non_taxable_adjustments . " | ";
                    $payroll->update([
                        "non_taxable_adjustment" => $non_taxable_adjustments,
                        "manual_deductions" => $manual_deductions,
                        "taxable_adjustment" => $taxable_adjustment,
                        "withholding_tax" => $withHoldingTax,
                        "net_pay" => round($newNetPay,2),
                        "additional_working_hours_total" => $additionalWorkingHoursTotal,
                        "remarks" => $remarks
                    ]);
                }

                if ($taxable_adjustment != $payroll->taxable_adjustment) {
                    $timeKeeping = TimeKeepingSummary::where("employee_id", $payroll->employee_id)
                    ->where("cut_off", $payroll->cut_off)
                    ->first();

                    $timeKeeping->adjustment_amount = $taxable_adjustment;

                    $remarks = 'TAXABLE ADJUSTMENT MANUALLY ENCODED : '.$taxable_adjustment . " | ";
                    $payroll->update([
                        "remarks" => $remarks
                    ]);

                    $newComputation = $payrollService->computePayroll($timeKeeping, true);

                    $payroll = $newComputation['payroll']->load([
                        "employee",
                        "employee.current_branch",
                        "employee.latest_bank_details",
                        "timekeeping_branch",
                        "timekeeping_summary",
                    ]);
                }

                DB::commit();
                return $this->sendResponse([
                    "message" => "Payroll updated successfully",
                    "details" => $payroll
                ]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Payroll not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
