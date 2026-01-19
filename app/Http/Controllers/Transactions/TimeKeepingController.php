<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTimekeepingSummary;
use App\Jobs\TransferLogsToTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\Employee;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use App\Exports\Transactions\TimeKeepingExport;
use App\Exports\Transactions\TimeKeepingSummaryExport;
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

            TransferLogsToTimeKeeping::dispatch($request->branch_id, [$start_time, $end_time]);
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
                "branch_id" => "required",
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

            $timeKeepingQuery = TimeKeeping::with(["employee", "employee.current_position", "payment"])
            ->where("branch_id", $request->branch_id)
            ->whereBetween("date", [$start_time, $end_time]);

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
                $timeKeepingQuery = $timeKeepingQuery->get();
                return Excel::download(new TimeKeepingExport($timeKeepingQuery), "time_keeping.xls");
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
                "branch_id" => "required",
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

            $timeKeepingSummaryQuery = TimeKeepingSummary::with(["employee", "lates", "branch" ,"employee.current_position", "employee.current_branch", "employee.timekeeping"])
                ->where("timekeeping_summary.branch_id", $request->branch_id)
                ->where("cut_off", $start_time.'-'.$end_time)
                ->join("employees", "timekeeping_summary.employee_id", "=", "employees.id")
                ->orderBy("employees.last_name", "ASC")
                ->select("timekeeping_summary.*");
            
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
                return Excel::download(new TimeKeepingSummaryExport($timeKeepingSummaryQuery), "time_keeping_summary.xls");
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
                "branch_id" => "required",
                "cutoff" => "required",
                "year" => "required",
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

            $cutOff = [
                "start_time" => $start_time,
                "end_time" => $end_time
            ];

            $fetchedCutOff = Cutoff::where("cut_off", $start_time."-".$end_time)->first();

            if ($fetchedCutOff && ($fetchedCutOff->is_timekeeping_locked || $fetchedCutOff->is_cutoff_locked)) {
                return $this->sendError(["message" => "Timekeeping or Cutoff is already locked."]);
            }

            Bus::chain([
                new TransferLogsToTimeKeeping($request->branch_id, $cutOff),
                new GenerateTimekeepingSummary($request->branch_id, $cutOff)
            ])->dispatch();

            $branch = Branch::find($request->branch_id);
            $user = Auth::user();
            activity()->causedBy($user)->useLog("Timekeeping Generation")
            ->log('User generated timekeeping of '.$branch->name ?? "undefined"." branch." );
            activity()->causedBy($user)->useLog("Timekeeping Summary Generation")
            ->log('User generated timekeeping summary of '.$branch->name ?? "undefined"." branch." );

            return $this->sendResponse(["message" => "Timekeeping are in queue for processing."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTimekeepingSummary(Request $request) {
        $request["export"] = true;

        return $this->getAllTimeKeepingSummary($request);
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
