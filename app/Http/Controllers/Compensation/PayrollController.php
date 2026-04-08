<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Http\Services\PayrollService;
use App\Jobs\GenerateBranchPayroll;
use App\Models\Incentives;
use App\Models\Payroll;
use App\Models\Tardiness;
use App\Models\Tax;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\PayrollDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Str;
use Log;

class PayrollController extends Controller
{
    public function getAllPayroll(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;
            $currentCutOff = $this->getCurrentCutoff();

            $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $request->year);
            $start_time = $dates["start_date"] ?? $currentCutOff['start_time'];
            $end_time = $dates["end_date"] ?? $currentCutOff['end_time'];

            $payroll = Payroll::with(["employee", "position", "details", "timekeeping_branch"])
            ->where("cut_off", $start_time."-".$end_time);

            if ($request->has(["branch_id"])) {
                $payroll = $payroll->where("timekeeping_branch_id", $request->branch_id);
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
                "timekeeping_branch",
                "details",
                "position",
                "payroll_list.current_bank_details"
            ])->find($id);

            if ($payroll) {
                $payroll->details->undertime_minutes = json_decode($payroll->details->undertime_minutes) ?? [];
                $payroll->details->tardiness_minutes = json_decode($payroll->details->tardiness_minutes) ?? [];
                $year = Carbon::parse($payroll->pay_date)->year;

                $incentives = Incentives::where("employee_id", $payroll->employee_id)
                ->where("pay_date", $payroll->pay_date)
                ->first();

                $tardinessOffenses = Tardiness::with(["tardiness_sanction"])
                ->where("employee_id", $payroll->employee_id)
                ->where("status", "Approved")
                ->whereIn("year", [$year - 1, $year])
                ->orderBy("id", "DESC")
                ->limit(5)
                ->get();

                $payroll["incentives"] = $incentives;
                $payroll["tardiness_offenses"] = $tardinessOffenses;
                return $this->sendResponse($payroll);
            }

            return $this->sendError(["message" => "Payroll not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
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

    public function generateBranchPayroll(Request $request) {
        try {

            $request->validate([
                "branch_id" => "required|array"
            ]);

            $branches = Branch::get()
            ->pluck('name', 'id')
            ->toArray();

            $cutOff = Cutoff::where("status", "active")->first();

            if ($cutOff) {
                $initializedJson = [];

                if ($cutOff->initializations) {
                    $initializedJson = json_decode($cutOff->initializations, true);
                }

                $newJson = [
                    [
                        "generated_at" => now(),
                        "generated_by" => Auth::user()->full_name
                    ],
                    ...$initializedJson
                ];

                $cutOff->update([
                    "initializations" => json_encode($newJson)
                ]);
            }

            foreach ($request->branch_id as $branch_id) {
                $branch = $branches[$branch_id];
                $context = $branch ?? "Unknown Branch";

                GenerateBranchPayroll::dispatch($branch_id, Auth::id(), $context);
            }

            return $this->sendResponse([
                "message" => "Payroll is generating."
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function showPayrollComputation(Request $request, $payroll_id) {
        try {
            $payrollService = new PayrollService();
            $payroll = Payroll::with([
                "employee",
                "timekeeping_branch",
                "details",
                "position",
                "payroll_list.current_bank_details"
            ])->find($payroll_id);

            if ($payroll) {
                $cutOff = Cutoff::where("pay_date", $payroll->pay_date)->first();
                $details = $payroll->details;
                $details->regular_days = $request->regular_days;
                $details->special_leave = $request->special_leave;
                $details->cancelled_off = $request->cancelled_off;
                $details->special_holiday = $request->special_holiday;
                $details->legal_holiday = $request->legal_holiday;
                $details->regular_overtime = $request->regular_overtime;
                $details->night_differential = $request->night_differential;
                $details->night_differential_overtime = $request->night_differential_overtime;
                $details->legal_holiday_overtime = $request->legal_holiday_overtime;
                $details->special_holiday_overtime = $request->special_holiday_overtime;
                $details->adjustment_amount = $request->adjustment_amount;
                $details->non_taxable_adjustment = $request->non_taxable_adjustment;
                $details->deductions = $request->deductions;
                $details->is_complete_documents = $request->is_complete_documents;

                $details->is_manual_statutory_entry = $request->is_manual_statutory_entry;
                $details->employee_sss = $request->employee_sss;
                $details->employer_sss = $request->employer_sss;
                $details->employee_pagibig = $request->employee_pagibig;
                $details->employer_pagibig = $request->employer_pagibig;
                $details->employee_philhealth = $request->employee_philhealth;
                $details->employer_philhealth = $request->employer_philhealth;
                $details->withholding_tax = $request->withholding_tax;

                $newTardiness = 0;
                $newUndertime = 0;

                foreach ($request->tardiness_minutes as $td) {
                    $newTardiness += $td["minutes"];
                }

                foreach ($request->undertime_minutes as $ut) {
                    $newUndertime += $ut["minutes"];
                }

                $details->tardiness = $newTardiness;
                $details->undertime = $newUndertime;

                $payrollComp = $payrollService->computePayrollV2($details, $cutOff, true);

                if ($payrollComp) {
                    foreach ($payrollComp as $key => $value) {
                        $payroll[$key] = $value;
                    }
                    $branch = Branch::find($request->timekeeping_branch_id);
                    $payroll->details = $details;
                    $payroll->timekeeping_branch_id = $request->timekeeping_branch_id ?? $payroll->timekeeping_branch_id;
                    $payroll->timekeeping_branch = $branch ?? $payroll->timekeeping_branch;
                    $payroll->details->undertime_minutes = $request->undertime_minutes ?? [];
                    $payroll->details->tardiness_minutes = $request->tardiness_minutes ?? [];
                    $payroll->details->deductions = $request->deductions;

                    if ($request->save_changes) {
                        PayrollDetail::where("payroll_id", $payroll->id)
                        ->update([
                            "regular_days" => $request->regular_days,
                            "special_leave" => $request->special_leave,
                            "cancelled_off" => $request->cancelled_off,
                            "special_holiday" => $request->special_holiday,
                            "legal_holiday" => $request->legal_holiday,
                            "regular_overtime" => $request->regular_overtime,
                            "night_differential" => $request->night_differential,
                            "night_differential_overtime" => $request->night_differential_overtime,
                            "legal_holiday_overtime" => $request->legal_holiday_overtime,
                            "special_holiday_overtime" => $request->special_holiday_overtime,
                            "adjustment_amount" => $request->adjustment_amount,
                            "undertime" => $newUndertime,
                            "tardiness" => $newTardiness,
                            "is_manual_statutory_entry" => $request->is_manual_statutory_entry,
                            "is_complete_documents" => $request->is_complete_documents,
                            "undertime_minutes" => json_encode($request->undertime_minutes),
                            "tardiness_minutes" => json_encode($request->tardiness_minutes)
                        ]);

                        Payroll::where("id", $payroll->id)
                        ->update([
                            "salary" => $payrollComp->salary,
                            "tardiness_amount" => $payrollComp->tardiness_amount,
                            "undertime_amount" => $payrollComp->undertime_amount,
                            "overtime_amount" =>  $payrollComp->overtime_amount,
                            "legal_holiday_amount" => $payrollComp->legal_holiday_amount,
                            "legal_holiday_overtime_amount" => $payrollComp->legal_holiday_overtime_amount,
                            "special_holiday_amount" => $payrollComp->special_holiday_amount,
                            "special_holiday_overtime_amount" => $payrollComp->special_holiday_overtime_amount,
                            "night_differential_amount" => $payrollComp->night_differential_amount,
                            "night_differential_overtime_amount" => $payrollComp->night_differential_overtime_amount,
                            "taxable_adjustment" => $payrollComp->taxable_adjustment,
                            "non_taxable_adjustment" => $payrollComp->non_taxable_adjustment,
                            "gross_pay" => $payrollComp->gross_pay,
                            "employee_sss" => $payrollComp->employee_sss,
                            "employer_sss" => $payrollComp->employer_sss,
                            "employee_pagibig" => $payrollComp->employee_pagibig,
                            "employer_pagibig" => $payrollComp->employer_pagibig,
                            "employee_philhealth" => $payrollComp->employee_philhealth,
                            "employer_philhealth" => $payrollComp->employer_philhealth,
                            "withholding_tax" => $payrollComp->withholding_tax,
                            "deductions" => $request->deductions,
                            "total_deductions" => $payrollComp->total_deductions,
                            "manual_deductions" => $payrollComp->manual_deductions,
                            "cancelled_off" => $payrollComp->cancelled_off,
                            "net_pay" => $payrollComp->net_pay,
                            "taxable_income" => $payrollComp->taxable_income,
                            "contribution_total" => $payrollComp->contribution_total,
                            "additional_working_hours_total" => $payrollComp->additional_working_hours_total,
                            "working_hours_deduction_total" => $payrollComp->working_hours_deduction_total,
                            "status" => "initial",
                            "special_leave" => $payrollComp->special_leave,
                            "timekeeping_branch_id" => $request->timekeeping_branch_id,
                        ]);
                    }
                    return $this->sendResponse($payroll);
                }
            }   

            return $this->sendError(["message" => "Payroll not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
