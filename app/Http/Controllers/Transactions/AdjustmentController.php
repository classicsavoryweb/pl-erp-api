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

            if ($request->has(["date_from", "date_to"])) {
                $adjustments = $adjustments->whereBetween('date', [$request->date_from, $request->date_to]);
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
                "type" => "required"
            ]);

            $employee = Employee::where("employee_number", $request->employee_number)->first();
            
            if ($employee) {
                $oldTimeKeeping = TimeKeeping::where("employee_id", $employee->id)
                ->where("date", $request->date)
                ->first();
                $log = BiotimeLog::where("employee_id", $employee->id)
                ->where("date", $request->date)
                ->first();

                $schedule = ScheduleMasterlist::where("selection", $request->schedule)->first();

                $isExisting = Adjustment::where("employee_id", $employee->id)
                ->where("date", $request->date)
                ->where("status", "Approved")
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
                        "branch_id" => $log->branch_id
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
                    $log->branch_id,
                    $log,
                    $updatedPunches,
                    $schedule->selection,
                    $employee,
                    new AdjustmentTimeKeeping()
                );

                $adjustmentTimeKeeping = AdjustmentTimeKeeping::where("biotime_log_id", $log->id)
                ->first();
                $this->timeKeepingService->computePayment($adjustmentTimeKeeping, $schedule->selection, true);
                $adjustmentPayment = TimeKeepingPayment::where("adjustment_timekeeping_id", $adjustmentTimeKeeping->id)->first();
                $originalPayment = TimeKeepingPayment::where("timekeeping_id", $oldTimeKeeping->id)->first();

                $cancelled_off = $adjustmentPayment->cancelled_off - $originalPayment->cancelled_off;
                $days = 0;

                if ($adjustmentPayment && !$originalPayment) {
                    $days += 1;
                }
                $adjustment->update([
                    "days" => $days,
                    "cancelled_rd" => $cancelled_off,
                    "taxable_amount" => round($adjustmentPayment->total_amount - $originalPayment->total_amount, 2) ,
                    "amount_paid" => round($originalPayment->total_amount, 2),
                    "correct_amount_paid" => round($adjustmentPayment->total_amount, 2),
                    "actual_schedule" => $schedule->selection,
                    "type" => $request->type,
                    "status" => "Pending",
                    "added_by" => Auth::user()->id,
                    "added_at" => now(),
                ]);
            }
            DB::commit();
            return $this->sendResponse(["message" => ""]);
        } catch (Exception $e) {
            Log::error($e);
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
}
