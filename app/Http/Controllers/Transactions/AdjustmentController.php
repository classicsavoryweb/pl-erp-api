<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Services\TimeKeepingService;
use App\Jobs\GenerateTimekeepingSummary;
use App\Models\Adjustment;
use App\Models\AdjustmentPunch;
use App\Models\AdjustmentTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\BiotimePunch;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Str;
use DB;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Log;

class AdjustmentController extends Controller
{
    protected $timeKeepingService;

    public function __construct() {
        $this->timeKeepingService = new TimeKeepingService();
    }

    public function getAllAdjustments(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $adjustments = Adjustment::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $adjustments = $adjustments->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $adjustments = $adjustments->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    if ($column === "full_name") {
                        $adjustments = $adjustments->whereHas("employee", function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $adjustments = $adjustments->where($column, 'LIKE', "%$keyword%");
                    }
                }
            }

            if ($request->has(["status"])) {
                $adjustments = $adjustments->where("status", $request->status);
            }

            if ($request->has('cutoff') && $request->filled('cutoff.start_time') && $request->filled('cutoff.end_time')) {
                $year = $request->year ?? Carbon::now()->year;
                $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $year);
                $start_time = $dates["start_date"];
                $end_time = $dates["end_date"];

                $adjustments = $adjustments->whereBetween("date", [$start_time, $end_time]);
            }

            $adjustments = $adjustments->with([
                "employee",
                "employee.current_position"
            ])
            ->paginate($perPage);

            return $this->sendResponse($adjustments);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createAdjustments(Request $request) {
        try {
            DB::beginTransaction();

            $request->validate([
                "employee_number" => "required",
                "date" => "required",
                "schedule" => "required",
                "type" => "required",
                "branch_id" => "required",
            ]);

            $employee = Employee::where("employee_number", $request->employee_number)->first();
            
            if ($employee) {
                $oldTimeKeeping = TimeKeeping::where("employee_id", $employee->id)
                ->where("date", $request->date)
                ->first();
                
                $adjustmentIsOnActiveCutOff = Cutoff::where("status", "active")
                ->where("start_date", '=<', $request->date)
                ->first();
                
                if ($adjustmentIsOnActiveCutOff) {
                    return $this->sendError(["message" => "You cannot adjust dates on current cutoff"]);
                }

                $log = BiotimeLog::where("employee_id", $employee->id)
                    ->where("date", $request->date)
                    ->first();
                if (!$log) {
                    $log = (object) [
                        "id" => 0,
                        "date" => $request->date,
                        "timetable" => $request->schedule,
                    ];
                }
                $schedule = ScheduleMasterlist::where("selection", $request->schedule)->first();

                $isExisting = Adjustment::where("employee_id", $employee->id)
                    ->where("date", $request->date)
                    ->whereIn("status", ["Pending", "Approved"])
                    ->first();

                if ($isExisting) {
                    return $this->sendError(["message" => "Adjustments already existing."]);
                }

                $adjustment = Adjustment::create([
                    "employee_id" => $employee->id,
                    "biometric_number" => $employee->biometric_number,
                    "date" => $request->date,
                ]);

                foreach($request->punches as $punch) {
                    $biotimePunch = null;

                    if ($log instanceof BiotimeLog) {
                        $biotimePunch = $log->punches()
                        ->where("punch_time", $punch["punch_time"])
                        ->first();
                    }

                    AdjustmentPunch::create([
                        "adjustment_id" => $adjustment->id,
                        "punch_time" => $punch["punch_time"],
                        "date" => $request->date,
                        "branch_id" => $biotimePunch->branch_id ?? $request->branch_id
                    ]);
                }

                $scheduleStart = Carbon::parse($request->date. " " .$schedule->start_time)->seconds(0);
                $scheduleEnd = Carbon::parse($request->date. " " .$schedule->end_time)->seconds(0);

                if ($scheduleEnd->isBefore($scheduleStart)) {
                    $date = Carbon::parse($request->date)->addDay()->format('Y-m-d');
                    $scheduleEnd = Carbon::parse($date. " " .$schedule->end_time)->seconds(0);
                }

                $updatedPunches = AdjustmentPunch::where("date", $request->date)
                ->where("adjustment_id", $adjustment->id)   
                ->orderBy("punch_time", "ASC")
                ->get();

                $adjustmentTimeKeeping = $this->timeKeepingService->computeTimeKeeping(
                    $request->branch_id,
                    $log,
                    $updatedPunches,
                    $schedule->selection,
                    $employee,
                    new AdjustmentTimeKeeping(),
                    true,
                    "taxable adjustment"
                );

                $adjustmentTimeKeeping->paid_holiday = $oldTimeKeeping->paid_holiday ?? null;
                $adjustmentTimeKeeping->save();

                $adjustmentPayment = $this->timeKeepingService->computePayment(
                    $adjustmentTimeKeeping, 
                    true,
                    $request->schedule,
                );

                $originalPayment = null;
                
                $cancelled_off = 0;
                $taxable_amount = 0;
                $amount_paid = 0;
                $days = 0;

                if ($oldTimeKeeping) {
                    $originalPayment = $this->timeKeepingService->computePayment($oldTimeKeeping,  false);
                }

                if (!in_array($schedule->selection,["REST DAY", "NO SCHEDULE"])) {
                    $days = 1;
                    $originalDays = 0;
                    $hours = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;

                    if ($adjustmentTimeKeeping->final_worked_hours == $hours / 2) {
                        $days = 0.5;
                    }

                    if ($oldTimeKeeping) {
                        if ($oldTimeKeeping->final_worked_hours == $hours / 2) {
                            $originalDays = 0.5;
                        } else {
                            $originalDays = 1;
                        }
                    }

                    $days = $days - $originalDays;
                }

                $taxable_amount = $adjustmentPayment->total_amount - ($originalPayment->total_amount ?? 0);
                $amount_paid = $originalPayment->total_amount ?? 0;

                if ($taxable_amount == 0) {
                    return $this->sendError(["message" => "Taxable adjustment computed id 0.00"]);
                }

                $adjustment->update([
                    "days" => $days,
                    "cancelled_rd" => $cancelled_off,
                    "taxable_amount" => round($taxable_amount, 2),
                    "amount_paid" => round($amount_paid, 2),
                    "correct_amount_paid" => round($adjustmentPayment->total_amount, 2),
                    "actual_schedule" => $schedule->selection,
                    "type" => $request->type,
                    "status" => "Approved",
                    "added_by" => Auth::user()->id,
                    "added_at" => now(),
                ]);

                $cutOffFromAdjustment = $this->getCutOffForDate($request->date);
                $activeCutOff = Cutoff::where("status", "active")->first();
                $timeKeeping = TimeKeeping::whereBetween("date", [$activeCutOff->start_date, $activeCutOff->end_date])
                ->where("date", ">", $cutOffFromAdjustment['end_time'])
                ->where("employee_id", $employee->id)
                ->first();

                if ($timeKeeping) {
                    GenerateTimekeepingSummary::dispatchSync(
                        $timeKeeping->branch_id,
                        [
                            "start_time" => $activeCutOff->start_date, 
                            "end_time" => $activeCutOff->end_date
                        ],
                        Auth::id(),
                        $timeKeeping->employee_id,
                        "Timekeeping added taxable adjustment",
                        true
                    );
                }
            }
            DB::commit();
            return $this->sendResponse(["message" => "Adjustment created successfully"]);
        } catch (Exception $e) {
            Log::error($e);
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editAdjustments(Request $request, $id) {
        try {
            $request->validate([
                "taxable_amount" => "required|numeric",
                "correct_amount_paid" => "required|numeric",
            ]);

            DB::beginTransaction();
            $adjustment = Adjustment::find($id);

            if (!$adjustment) {
                DB::rollBack();
                return $this->sendError(["message" => "Adjustment not found"]);
            }

            if ($adjustment->status === "Adjusted") {
                DB::rollBack();
                return $this->sendError(["message" => "Adjustment already adjusted and cannot be edited"]);
            }

            $taxableAmount = round((float) $request->taxable_amount, 2);
            $correctAmountPaid = round((float) $request->correct_amount_paid, 2);

            $user = Auth::user();
            $userLabel = $user?->proper_full_name ?? $user?->full_name ?? $user?->email ?? "Unknown User";
            $timestamp = now()->format("Y-m-d H:i");
            $remarksEntry = "ADJUSTMENT AMOUNT EDIT: taxable={$taxableAmount} correct={$correctAmountPaid} by {$userLabel} at {$timestamp} |";

            $remarks = trim($adjustment->remarks ?? "");
            $remarks = trim($remarks . " " . $remarksEntry);

            $adjustment->update([
                "taxable_amount" => $taxableAmount,
                "correct_amount_paid" => $correctAmountPaid,
                "remarks" => $remarks,
            ]);

            DB::commit();
            return $this->sendResponse([
                "message" => "Adjustment updated successfully",
                "adjustment" => $adjustment
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAdjustment($id) {
        try {
            $adjustment = Adjustment::find($id);

            if ($adjustment) {
                $employee = $adjustment->employee;
                $adjustmentTimeKeeping = AdjustmentTimeKeeping::with("schedule")
                ->where("employee_id", $employee->id)
                ->where("date", $adjustment->date)
                ->first();
                $originalTimeKeeping = TimeKeeping::with("schedule")
                ->where("employee_id", $employee->id)
                ->where("date", $adjustment->date)
                ->first();
    
                return $this->sendResponse([
                    "adjustment" => $adjustment,
                    "adjustment_timekeeping" => $adjustmentTimeKeeping,
                    "original_timekeeping" => $originalTimeKeeping
                ]);
            }
            
            return $this->sendError(["message" => "Adjustment not found"]);
        } catch (Exception $e) {
            Log::info($e);
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function adjustTimeKeepingWithOtherOvertime(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "timekeeping_id" => "required",
                "overtime_id" => "required"
            ]);

            $overtime = Overtime::find($request->overtime_id);
            $timeKeeping = TimeKeeping::find($request->timekeeping_id);

            if ($overtime && $timeKeeping) {
                $employee = $overtime->employee;
                $overtimeHours = $overtime->total_hours;
                $schedule = $timeKeeping->schedule;
                $oldtimeKeepingPayment = $this->timeKeepingService->computePayment($timeKeeping);

                if ($timeKeeping->regular_overtime > 0 && $overtimeHours > $timeKeeping->regular_overtime) {
                    $overtimeHours = $timeKeeping->regular_overtime;
                } else if ($timeKeeping->legal_holiday_overtime > 0 && $overtimeHours > $timeKeeping->legal_holiday_overtime) {
                    $overtimeHours = $timeKeeping->legal_holiday_overtime;
                } else if ($timeKeeping->special_holiday_overtime > 0 && $overtimeHours > $timeKeeping->special_holiday_overtime) {
                    $overtimeHours = $timeKeeping->special_holiday_overtime;
                }

                $adjustedTimeKeeping = AdjustmentTimeKeeping::create([
                    ...$timeKeeping->toArray(),
                    "approved_overtime" => $overtimeHours
                ]);

                $adjustedTimeKeeping->paid_holiday = $timeKeeping->paid_holiday ?? null;
                $adjustedTimeKeeping->save();

                $adjustedTimeKeepingPayment = $this->timeKeepingService->computePayment(
                    $adjustedTimeKeeping,
                    true
                );

                $log = BiotimeLog::where("employee_id", $employee->id)
                ->where("date", $timeKeeping->date)
                ->first();

                $taxable_amount = $adjustedTimeKeepingPayment->total_amount - ($oldtimeKeepingPayment->total_amount ?? 0);
                $amount_paid = $oldtimeKeepingPayment->total_amount ?? 0;
                $adjustment = Adjustment::create([
                    "employee_id" => $employee->id,
                    "biometric_number" => $employee->biometric_number,
                    "date" => $timeKeeping->date,
                    "days" => 0,
                    "cancelled_rd" => 0,
                    "taxable_amount" => round($taxable_amount, 2) ,
                    "amount_paid" => round($amount_paid, 2),
                    "correct_amount_paid" => round($adjustedTimeKeepingPayment->total_amount, 2),
                    "actual_schedule" => $schedule->timetable,
                    "type" => "Overtime Force Adjustments",
                    "status" => "Approved",
                    "added_by" => Auth::user()->id,
                    "added_at" => now(),
                ]);

                foreach($log->punches as $punch) {
                    AdjustmentPunch::create([
                        "adjustment_id" => $adjustment->id,
                        "punch_time" => $punch->punch_time,
                        "date" => $timeKeeping->date,
                        "branch_id" => $punch->branch_id ?? null
                    ]);
                }

                $currentCutOff = $this->getCurrentCutOff();
                $cutOffPaid = Carbon::parse($currentCutOff["start_time"])->format("m/d/Y")." TO ". Carbon::parse($currentCutOff['end_time'])->format("m/d/Y");
                $remainingHours = $overtime->total_hours - $overtimeHours;
                $payrollStatus = $remainingHours >= 1 ? "unpaid" : "paid";

                $overtime->update([
                    "hours_paid" => $overtimeHours,
                    "cutoff_paid" => $cutOffPaid,
                    "payment_type" => "force adjustment",
                    "adjustment_timekeeping_id" => $adjustedTimeKeeping->id,
                    "status" => $remainingHours >= 1 ? "Partialy Paid" : "Paid"
                ]);


                DB::commit();
                return $this->sendResponse(["message" => "Overtime successfully adjusted"]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Overtime or Timekeeping cant be found."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function approveAdjustments(Request $request, $id) {
        try {
            DB::beginTransaction();
            $adjustment = Adjustment::find($id);

            if ($adjustment) {
                $adjustment->update([
                    "status" => "Approved",
                    "approved_by" => Auth::user()->id,
                    "approved_at" => now(),
                ]);
                DB::commit();
                return $this->sendResponse(["message" => "Adjustment approved successfully"]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Adjustment not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function unapproveAdjustments(Request $request, $id) {
        try {
            DB::beginTransaction();
            $adjustment = Adjustment::find($id);

            if ($adjustment) {
                $adjustment->update([
                    "status" => "Unapproved",
                    "unapproved_by" => Auth::user()->id,
                    "unapproved_at" => now(),
                ]);

                DB::commit();
                return $this->sendResponse(["message" => "Adjustment unapproved successfully"]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Adjustment not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function voidAdjustments(Request $request, $id) {
        try {
            DB::beginTransaction();
            $adjustment = Adjustment::find($id);

            if ($adjustment) {
                $adjustment->update([
                    "status" => "Voided",
                ]);
                DB::commit();
                return $this->sendResponse(["message" => "Adjustment voided successfully"]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Adjustment not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
