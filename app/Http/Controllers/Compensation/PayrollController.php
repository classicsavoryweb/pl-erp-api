<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\Incentives;
use App\Models\Payroll;
use App\Models\TimeKeeping;
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

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $payroll = Payroll::with(["employee","employee.current_branch", "timekeeping_summary",])
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
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($id);

            if ($payroll) {
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

                $payroll["tardiness_list"] = $tardiness;
                $payroll["undertime_list"] = $undertime;
                $payroll["incentives"] = $incentives;

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

    public function updatePayrollIncentive(Request $request, $payrollId) {
        try {
            DB::beginTransaction();

            $request->validate([
                "adjustment_percent" => "required"
            ]);

            $payroll = Payroll::with(["employee",
                "employee.current_branch",
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($payrollId);

            if ($payroll) {

                $employee = $payroll->employee;

                $adjustment_percent = $request->adjustment_percent;

                $incentives = Incentives::where("employee_id", $payroll->employee_id)
                ->where("pay_date", $payroll->pay_date)
                ->first();

                $amount_rate = $incentives->amount_rate;
                $adjustment_days = $incentives->adjustment_days;
                $additional_incentive = $incentives->additional_incentive;

                $startDate = $payroll->start_date;
                $endDate = $payroll->end_date;

                $firstCutOff = $this->getCutOffForDate($startDate);
                $secondCutOff = $this->getCutOffForDate($endDate);

                $days = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 11 : 13; 

                $firstCutOffWorkDays = $employee->getCutOffWorkDays($firstCutOff);
                $secondCutOffWorkDays = $employee->getCutOffWorkDays($secondCutOff);

                $monthShouldBeWorkDays = ($firstCutOffWorkDays > 0 ? $firstCutOffWorkDays : $days)
                + ($secondCutOffWorkDays > 0 ? $secondCutOffWorkDays : $days);

                $final_incentive_amount = ($amount_rate + $additional_incentive) - ((($amount_rate + $additional_incentive) * ($adjustment_percent / 100)) + ((($amount_rate + $additional_incentive) / $monthShouldBeWorkDays) * $adjustment_days));

                $incentives->update([
                    "incentive_amount" => $final_incentive_amount,
                    "adjustment_percent" => $adjustment_percent,
                ]);

                DB::commit();
                return $this->sendResponse([
                    "message" => "Incentive updated successfully",
                    "incentives" => $incentives
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

            $payroll = Payroll::with(["employee",
                "employee.current_branch",
                "employee.latest_bank_details",
                "timekeeping_branch",
                "timekeeping_summary",
            ])->find($payrollId);

            if ($payroll) {
                $remarks = $request->remarks ?? null;
                $manual_deductions = $request->manual_deductions ?? 0;
                $non_taxable_adjustments = $request->non_taxable_adjustment ?? 0;

                $newNetPay = $payroll->net_pay;

                if ($non_taxable_adjustments != $payroll->$non_taxable_adjustments) {
                    $newNetPay += $non_taxable_adjustments - $payroll->$non_taxable_adjustments;
                }
                
                $amountToDeduct = 0;

                if ($manual_deductions != $payroll->manual_deductions) {
                    $amountToDeduct = $manual_deductions - $payroll->manual_deductions;
                    $newNetPay -= $amountToDeduct;
                }

                $payroll->update([
                    "non_taxable_adjustment" => $non_taxable_adjustments,
                    "manual_deductions" => $manual_deductions,
                    "net_pay" => $newNetPay,
                    "remarks" => $remarks
                ]);

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
