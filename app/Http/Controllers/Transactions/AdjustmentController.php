<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Services\TimeKeepingService;
use App\Models\Adjustment;
use App\Models\AdjustmentPunch;
use App\Models\AdjustmentTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Overtime;
use App\Models\ScheduleMasterlist;
use App\Models\TimeKeeping;
use Illuminate\Http\Request;
use Str;
use DB;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

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
                "time_keeping"
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
                        "branch_id" => $punch["branch_id"]
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

                $adjustmentCutOff = $this->getCutOff($request->date);
                $payment = $employee->payPerDay($adjustmentCutOff);

                $this->timeKeepingService->computeTimeKeeping(
                    $log->branch_id,
                    $log,
                    $updatedPunches,
                    $schedule,
                    $employee,
                    new AdjustmentTimeKeeping()
                );
                
                // $adjustment->update([
                //     "days" => $days,
                //     "cancelled_rd" => $cancelled_rd,
                //     "taxable_amount" => $taxable_amount,
                //     "amount_paid" => $amount_paid,
                //     "correct_amount_paid" => $correct_amount_paid,
                //     "actual_schedule" => $schedule->selection,
                //     "type" => $request->type,
                //     "status" => "Pending",
                //     "added_by" => Auth::user()->id,
                //     "added_at" => now(),
                // ]);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editAdjustments(Request $request, $id) {
        try {
            DB::beginTransaction();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function approveAdjustments(Request $request, $id) {
        try {
            DB::beginTransaction();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function unapproveAdjustments(Request $request, $id) {
        try {
            DB::beginTransaction();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
