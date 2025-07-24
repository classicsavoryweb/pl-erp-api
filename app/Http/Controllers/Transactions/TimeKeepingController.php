<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTimekeepingSummary;
use App\Jobs\TransferLogsToTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\Employee;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use DB;
use Str;

class TimeKeepingController extends Controller
{
    public function generateTimeKeeping(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required"
            ]);

            $currentCutOff = $this->getCurrentCutoff();
            $year = $request->year ?? Carbon::now()->year;

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            TransferLogsToTimeKeeping::dispatch($request->branch_id, [$start_time, $end_time]);
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

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $timeKeepingQuery = TimeKeeping::with(["employee", "employee.current_position"])
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

            $timeKeeping = $timeKeepingQuery->paginate($perPage);

            return $this->sendResponse($timeKeeping);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
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

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $timeKeepingSummaryQuery = TimeKeepingSummary::with(["employee", "lates", "employee.current_position"])
            ->where("timekeeping_summary.branch_id", $request->branch_id)
            ->where("cut-off", $start_time.'-'.$end_time)
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

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $cutOff = [
                "start_time" => $start_time,
                "end_time" => $end_time
            ];

            $employees = Employee::whereHas("timekeeping", function ($query) use ($request) {
                $query->where("branch_id", $request->branch_id);
            })
            ->where("status", "active")
            ->get();

            GenerateTimekeepingSummary::dispatch($employees, $cutOff);

            return $this->sendResponse(["message" => "Timekeeping are in queue for processing."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
