<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Services\TimeKeepingService;
use App\Jobs\GenerateTimekeepingSummary;
use App\Jobs\TransferLogsToTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\Adjustment;
use App\Models\Employee;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use App\Exports\Transactions\TimeKeepingExport;
use App\Exports\Transactions\TimeKeepingSummaryExport;
use App\Models\ScheduleTime;
use App\Models\Branch;
use App\Models\Cutoff;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use DB;
use Illuminate\Support\Facades\Auth;
use Str;
use Log;
use Illuminate\Support\Facades\Bus;

class TimeKeepingController extends Controller
{
    protected $timeKeepingService;

    public function __construct() {
        $this->timeKeepingService = new TimeKeepingService();
    }

    public function triggerFetchBiotimeLogs(Request $request) {
        Artisan::call('app:fetch-biotime-attendance');

        return $this->sendResponse(["message" => "Fetching biotime"]);
    }

    public function generateTimeKeeping(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required"
            ]);

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

            TransferLogsToTimeKeeping::dispatch($request->branch_id, [$start_time, $end_time], Auth::id());
            $branch = Branch::find($request->branch_id);
            $user = Auth::user();
            activity()->causedBy($user)->useLog("Timekeeping Generation")
            ->log('User generated timekeeping of '.$branch->name ?? "undefined"." branch." );

            return $this->sendResponse(["message" => "Logs are in queue for processing."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllTimeKeeping(Request $request) {
        try {
            $request->validate([
                "branch_id" => "nullable",
                "cutoff" => "required",
                "year" => "required",
            ]);
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

            $timeKeepingQuery = TimeKeeping::with([
                "employee",
                "employee.current_position",
                "employee.schedule.schedule_type",
                "payment",
                "schedule",
                "biotime_log.punches"
            ])
            ->whereBetween("date", [$start_time, $end_time]);

            if (!empty($request->branch_id)) {
                $timeKeepingQuery = $timeKeepingQuery->where("branch_id", $request->branch_id);
            }

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $timeKeepingQuery = $timeKeepingQuery->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            })
                            ->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $relations = explode('.', $column);
                        $relatedColumn = array_pop($relations); 
                        $relationPath = implode('.', $relations); 

                        $timeKeepingQuery = $timeKeepingQuery->whereHas($relationPath, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }
                } else {
                    $timeKeepingQuery = $timeKeepingQuery->where($column, 'LIKE', "%$keyword%");
                }
            }

            $timeKeepingQuery = $timeKeepingQuery
            ->orderBy("employee_id", "ASC")
            ->orderBy("date", "ASC");

            if ($request->has(["export"]) && $request->export) {
                $scheduleQuery = ScheduleTime::with([
                    "employee",
                    "employee.current_position",
                    "employee.schedule.schedule_type",
                    "timekeeping.biotime_log.punches"
                ])
                ->where("schedule_time.branch_id", $request->branch_id)
                ->whereBetween("schedule_time.date", [$start_time, $end_time]);

                if ($request->has(["column_type", "key_word"])) {
                    $column = $request->column_type;
                    $keyword = $request->key_word;

                    if (Str::contains($column, '.')) {
                        [$relation, $relatedColumn] = explode('.', $column, 2);

                        if ($relatedColumn == "full_name") {
                            $scheduleQuery = $scheduleQuery->whereHas($relation, function ($query) use ($keyword) {
                                $query->whereAny(
                                    ["first_name", "last_name", "middle_name"],
                                    'LIKE',
                                    "%$keyword%"
                                )->orWhere(function ($q) use ($keyword) {
                                    $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                                })
                                ->orWhere(function ($query) use ($keyword) {
                                    $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                                });
                            });
                        } else {
                            $relations = explode('.', $column);
                            $relatedColumn = array_pop($relations);
                            $relationPath = implode('.', $relations);

                            $scheduleQuery = $scheduleQuery->whereHas($relationPath, function ($query) use ($relatedColumn, $keyword) {
                                $query->where($relatedColumn, 'LIKE', "%$keyword%");
                            });
                        }
                    } else {
                        $timekeepingColumns = [
                            "worked_hours",
                            "final_worked_hours",
                            "tardiness",
                            "undertime",
                            "regular_overtime",
                            "night_diff_hours",
                            "night_diff_overtime",
                            "legal_holiday_hours",
                            "legal_holiday_overtime",
                            "special_holiday_hours",
                            "special_holiday_overtime",
                            "cancelled_off_hours",
                            "approved_overtime",
                            "travel_time"
                        ];

                        if (in_array($column, $timekeepingColumns, true)) {
                            $scheduleQuery = $scheduleQuery->whereHas("timekeeping", function ($query) use ($column, $keyword) {
                                $query->where($column, 'LIKE', "%$keyword%");
                            });
                        } else {
                            $scheduleQuery = $scheduleQuery->where($column, 'LIKE', "%$keyword%");
                        }
                    }
                }

                $scheduleQuery = $scheduleQuery
                    ->orderBy("employee_id", "ASC")
                    ->orderBy("date", "ASC");

                $scheduleRows = $scheduleQuery->get();
                return Excel::download(new TimeKeepingExport($scheduleRows), "time_keeping.xls");
            }

            $timeKeeping = $timeKeepingQuery->paginate($perPage);

            return $this->sendResponse($timeKeeping);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTimekeeping(Request $request) {
        $request["export"] = true;

        return $this->getAllTimeKeeping($request);
    }

    public function getAllTimeKeepingSummary(Request $request) {
        try {
            $request->validate([
                "branch_id" => "nullable",
                "cutoff" => "required",
                "year" => "required",
            ]);
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

            $timeKeepingSummaryQuery = TimeKeepingSummary::with([
                "employee",
                "lates",
                "branch",
                "employee.current_position",
                "employee.current_branch",
                "employee.timekeeping",
                "employee.schedule.schedule_type",
                "employee.payroll_list",
            ])
                ->where("cut_off", $start_time.'-'.$end_time)
                ->join("employees", "timekeeping_summary.employee_id", "=", "employees.id")
                ->orderBy("employees.last_name", "ASC")
                ->select("timekeeping_summary.*");

            if (!empty($request->branch_id)) {
                $timeKeepingSummaryQuery = $timeKeepingSummaryQuery->where("timekeeping_summary.branch_id", $request->branch_id);
            }
            
            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $timeKeepingSummaryQuery = $timeKeepingSummaryQuery->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            })
                            ->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $relations = explode('.', $column);
                        $relatedColumn = array_pop($relations); 
                        $relationPath = implode('.', $relations); 

                        $timeKeepingSummaryQuery = $timeKeepingSummaryQuery->whereHas($relationPath, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }
                } else {
                    $timeKeepingSummaryQuery = $timeKeepingSummaryQuery->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $timeKeepingSummaryQuery = $timeKeepingSummaryQuery->get();
                return Excel::download(new TimeKeepingSummaryExport(
                    $timeKeepingSummaryQuery,
                    $start_time,
                    $end_time
                ), "time_keeping_summary.xls");
            }

            $timeKeepingSummary = $timeKeepingSummaryQuery->paginate($perPage);

            return $this->sendResponse($timeKeepingSummary);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function generateTimeKeepingSummary(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required|array",
            ]);

            $fetchedCutOff = Cutoff::where("status", "active")->first();

            if ($fetchedCutOff && ($fetchedCutOff->is_timekeeping_locked || $fetchedCutOff->is_cutoff_locked)) {
                return $this->sendError(["message" => "Timekeeping or Cutoff is already locked."]);
            }

            $cutOff = [
                "start_time" => $fetchedCutOff->start_date,
                "end_time" => $fetchedCutOff->end_date
            ];

            $branches = Branch::whereIn("id", $request->branch_id)
            ->pluck('name', 'id')
            ->toArray();
            
            foreach ($request->branch_id as $branch_id) {
                $branch = $branches[$branch_id];
                $branchName = $branch ?? "Unknown Branch";
    
                Bus::chain([
                    new TransferLogsToTimeKeeping($branch_id, $cutOff, Auth::id(), true),
                    new GenerateTimekeepingSummary($branch_id, $cutOff, Auth::id(), 0, $branchName)
                ])
                ->delay(now()->addSeconds(5))
                ->dispatch();

                $user = Auth::user();
                activity()->causedBy($user)->useLog("Timekeeping Generation")
                ->log('User generated timekeeping of '.$branch ?? "undefined"." branch." );
                activity()->causedBy($user)->useLog("Timekeeping Summary Generation")
                ->log('User generated timekeeping summary of '.$branch ?? "undefined"." branch." );
            }

            return $this->sendResponse(["message" => "Timekeeping are in queue for processing."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTimekeepingSummary(Request $request) {
        $request["export"] = true;

        return $this->getAllTimeKeepingSummary($request);
    }

    public function addTimeKeepingSummaryAdjustment(Request $request) {
        try {
            $request->validate([
                "timekeeping_summary_id" => "required|integer",
                "amount" => "required|numeric",
            ]);

            DB::beginTransaction();
            $summary = TimeKeepingSummary::with("employee")->find($request->timekeeping_summary_id);

            if (!$summary) {
                DB::rollBack();
                return $this->sendError(["message" => "Timekeeping summary not found"]);
            }

            $amount = round((float) $request->amount, 2);
            $user = Auth::user();
            $userLabel = $user?->proper_full_name ?? $user?->full_name ?? $user?->email ?? "Unknown User";
            $timestamp = now()->format("Y-m-d H:i");
            $remarksEntry = "MANUAL TAXABLE ADJUSTMENT: {$amount} by {$userLabel} at {$timestamp} |";
            $cutOff = $summary->cut_off ?? "";
            $cutOffStart = strlen($cutOff) >= 21 ? substr($cutOff, 0, 10) : null;
            $cutOffEnd = strlen($cutOff) >= 21 ? substr($cutOff, 11, 10) : null;
            $adjustmentDate = $cutOffEnd ?? now()->format("Y-m-d");
            
            $isActiveCutOff = Cutoff::where("cut_off", $cutOff)->first();

            if (!$isActiveCutOff || ($isActiveCutOff && $isActiveCutOff->status == 'inactive')) {
                return $this->sendError(["message" => "You cant edit inactive cutoff"]);
            }

            Adjustment::where("timekeeping_summary_id", $summary->id)
                ->where("type", "Manual Taxable Adjustment")
                ->whereIn("status", ["Approved", "Adjusted"])
                ->update([
                    "status" => "Voided",
                    "remarks" => DB::raw("CONCAT(IFNULL(remarks,''), ' Replaced by latest manual adjustment.')"),
                ]);

            Adjustment::create([
                "employee_id" => $summary->employee_id,
                "biometric_number" => $summary->employee?->biometric_number,
                "date" => $adjustmentDate,
                "days" => 0,
                "cancelled_rd" => 0,
                "taxable_amount" => $amount,
                "amount_paid" => 0,
                "correct_amount_paid" => 0,
                "actual_schedule" => null,
                "type" => "Manual Taxable Adjustment",
                "status" => "Adjusted",
                "remarks" => $remarksEntry,
                "added_by" => Auth::user()->id,
                "added_at" => now(),
                "timekeeping_summary_id" => $summary->id,
                "is_timekeeping_locked" => 0,
            ]);

            $adjustmentAmount = $amount;

            $remarks = trim($summary->remarks ?? "");
            $remarks = trim($remarks . " " . $remarksEntry);

            $summary->update([
                "adjustment_amount" => round($adjustmentAmount, 2),
                "remarks" => trim($remarks),
            ]);

            DB::commit();
            return $this->sendResponse([
                "message" => "Taxable adjustment added successfully",
                "adjustment_amount" => round($adjustmentAmount, 2),
            ]);
        } catch(Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getTimeKeepingSummaryAdjustmentLogs(Request $request) {
        try {
            $request->validate([
                "timekeeping_summary_id" => "required|integer",
            ]);

            $adjustments = Adjustment::with("added_by_user")
                ->where("timekeeping_summary_id", $request->timekeeping_summary_id)
                ->where("type", "Manual Taxable Adjustment")
                ->orderBy("created_at", "DESC")
                ->get();

            return $this->sendResponse($adjustments);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function updateTimeKeepingUndertime(Request $request) {
        try {
            $request->validate([
                "timekeeping_id" => "required",
                "tag" => "required|in:halfday,undertime,tardiness,tardiness_undertime,tardiness_halfday",
                "hours" => "nullable|numeric|min:0",
                "minutes" => "nullable|numeric|min:0|max:59",
                "tardiness_hours" => "nullable|numeric|min:0",
                "tardiness_minutes" => "nullable|numeric|min:0|max:59",
            ]);

            $timeKeeping = TimeKeeping::with([
                "employee",
                "employee.schedule.schedule_type",
                "schedule"
            ])->find($request->timekeeping_id);

            if (!$timeKeeping) {
                return $this->sendError(["message" => "Timekeeping not found"]);
            }

            $schedule = $timeKeeping->schedule;
            if (!$schedule) {
                return $this->sendError(["message" => "Schedule not found for timekeeping"]);
            }

            $cutOff = $this->getCutOffForDate($timeKeeping->date);
            
            $isActiveCutOff = Cutoff::where("start_date", $cutOff['start_time'])
            ->where("end_date", $cutOff['end_time'])
            ->first();

            if (!$isActiveCutOff || ($isActiveCutOff && $isActiveCutOff->status == 'inactive')) {
                return $this->sendError(["message" => "You cant edit inactive cutoff"]);
            }

            $hours = (int) ($request->hours ?? 0);
            $minutes = (int) ($request->minutes ?? 0);
            $totalMinutes = ($hours * 60) + $minutes;

            $tardinessHours = (int) ($request->tardiness_hours ?? 0);
            $tardinessMinutes = (int) ($request->tardiness_minutes ?? 0);
            $totalTardinessMinutes = ($tardinessHours * 60) + $tardinessMinutes;

            $scheduleType = $timeKeeping->employee?->schedule?->schedule_type?->name ?? "Normal Schedule";
            $hoursPerDay = $scheduleType === "Compressed Schedule" ? 9.5 : 8;

            $finalWorkedHours = $timeKeeping->final_worked_hours;
            if (in_array($request->tag, ["halfday", "tardiness_halfday"], true)) {
                $finalWorkedHours = $hoursPerDay / 2;
            } else if (in_array($request->tag, ["undertime", "tardiness", "tardiness_undertime"], true)) {
                $finalWorkedHours = $hoursPerDay;
            }

            $specialHolidayHours = $timeKeeping->special_holiday_hours;
            $specialHolidayOvertime = $timeKeeping->special_holiday_overtime;
            if (in_array($request->tag, ["halfday", "tardiness_halfday"], true)) {
                $specialHolidayHours = $specialHolidayHours ? $specialHolidayHours / 2 : $specialHolidayHours;
                $specialHolidayOvertime = $specialHolidayOvertime ? $specialHolidayOvertime / 2 : $specialHolidayOvertime;
            }

            $undertimeMinutes = $timeKeeping->undertime;
            if (in_array($request->tag, ["undertime", "halfday", "tardiness_undertime"], true)) {
                $undertimeMinutes = $totalMinutes;
            }

            $tardinessOverrideMinutes = $timeKeeping->tardiness;
            if (in_array($request->tag, ["tardiness", "tardiness_undertime", "tardiness_halfday"], true)) {
                $tardinessOverrideMinutes = $totalTardinessMinutes;
            }

            $timeKeeping->update([
                "undertime" => $undertimeMinutes,
                "tardiness" => $tardinessOverrideMinutes,
                "final_worked_hours" => $finalWorkedHours,
                "special_holiday_hours" => $specialHolidayHours,
                "special_holiday_overtime" => $specialHolidayOvertime,
                "is_manual_override" => true,
                "manual_tag" => $request->tag,
                "manual_undertime_minutes" => in_array($request->tag, ["undertime", "halfday", "tardiness_undertime"], true) ? $undertimeMinutes : null,
                "manual_tardiness_minutes" => in_array($request->tag, ["tardiness", "tardiness_undertime", "tardiness_halfday"], true) ? $tardinessOverrideMinutes : null,
            ]);

            $timeKeeping->refresh();

            GenerateTimekeepingSummary::dispatchSync(
                $timeKeeping->branch_id,
                $cutOff,
                Auth::id(),
                $timeKeeping->employee_id,
                "Timekeeping manual undertime update",
                true
            );

            return $this->sendResponse(["message" => "Timekeeping updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getTimeKeeping(Request $request) {
        try {
            $employee = Employee::where("employee_number", $request->employee_number)->first();
            $timeKeeping = TimeKeeping::with("biotime_log", "biotime_log.punches", "schedule" )
                ->where("date", $request->date)
                ->where("employee_id", $employee->id)
                ->first();
            return $this->sendResponse($timeKeeping);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
