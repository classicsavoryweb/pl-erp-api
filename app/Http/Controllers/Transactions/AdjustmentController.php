<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Services\TimeKeepingService;
use App\Models\Adjustment;
use App\Models\AdjustmentPunch;
use App\Models\AdjustmentTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\Employee;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingPayment;
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

                $start_time = $year . '-' . $request->cutoff['start_time'];
                $end_time = $year . '-' . $request->cutoff['end_time'];

                $adjustments = $adjustments->whereBetween("date", [$start_time, $end_time]);
            }

            $adjustments = $adjustments->with([
                "employee",
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

                if (!$oldTimeKeeping) {
                    return $this->sendError(["message" => "Timekeeping not found"]);
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
                    AdjustmentPunch::create([
                        "adjustment_id" => $adjustment->id,
                        "punch_time" => $punch["punch_time"],
                        "date" => $request->date,
                        "branch_id" => $request->branch_id
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

                $this->timeKeepingService->computeTimeKeeping(
                    $request->branch_id,
                    $log,
                    $updatedPunches,
                    $schedule->selection,
                    $employee,
                    new AdjustmentTimeKeeping()
                );

                $adjustmentTimeKeeping = AdjustmentTimeKeeping::where("biotime_log_id", $log->id)->first();
                $this->timeKeepingService->computePayment($adjustmentTimeKeeping, $schedule->selection, true);
                $adjustmentPayment = TimeKeepingPayment::where("adjustment_timekeeping_id", $adjustmentTimeKeeping->id)->first();
                $originalPayment = null;

                if ($oldTimeKeeping) {
                    $originalPayment = TimeKeepingPayment::where("timekeeping_id", $oldTimeKeeping->id)->first();
                }

                $cancelled_off = $adjustmentTimeKeeping->cancelled_off_hours - ($oldTimeKeeping->cancelled_off_hours ?? 0);
                $days = 0;

                if ($adjustmentPayment && !$originalPayment) {
                    $submittedScheduleTime = ScheduleTime::where("date", $request->date)
                    ->where("employee_id", $employee->id)
                    ->first();

                    if ($submittedScheduleTime->timetable && !in_array($submittedScheduleTime->timetable, ["REST DAY", "NO SCHEDULE"])) {
                        $days += 1;
                    } else {
                        if (!$submittedScheduleTime) {
                            $days += 1;
                        }
                    }
                }

                $taxable_amount = $adjustmentPayment->total_amount - ($originalPayment->total_amount ?? 0);
                $amount_paid = $originalPayment->total_amount ?? 0;
                $adjustment->update([
                    "days" => $days,
                    "cancelled_rd" => $cancelled_off,
                    "taxable_amount" => round($taxable_amount, 2) ,
                    "amount_paid" => round($amount_paid, 2),
                    "correct_amount_paid" => round($adjustmentPayment->total_amount, 2),
                    "actual_schedule" => $schedule->selection,
                    "type" => $request->type,
                    "status" => "Approved",
                    "added_by" => Auth::user()->id,
                    "added_at" => now(),
                ]);
            }
            DB::commit();
            return $this->sendResponse(["message" => "Adjustment created successfully"]);
        } catch (Exception $e) {
            Log::error($e);
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
