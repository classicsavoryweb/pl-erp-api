<?php

namespace App\Http\Controllers\Transactions;

use App\Exports\Transactions\TravelOvertimeExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TravelOvertime;
use Carbon\Carbon;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Str;

class TravelOvertimeController extends Controller
{
    public function getAllTravelOvertime(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $overtime = TravelOvertime::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $overtime = $overtime->whereHas($relation, function ($query) use ($keyword) {
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

                        $overtime = $overtime->whereHas($relationPath, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }
                } else {
                    $overtime = $overtime->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $overtime = $overtime->where("status", $request->status);
            }

            if ($request->has(['branch_id'])) {
                $overtime = $overtime->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has('cutoff') && $request->filled('cutoff.start_time') && $request->filled('cutoff.end_time')) {
                $year = $request->year ?? Carbon::now()->year;
                $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $year);
                $start_time = $dates["start_date"];
                $end_time = $dates["end_date"];

                $overtime = $overtime->whereBetween("date", [$start_time, $end_time]);
            }

            $overtime = $overtime->with([
                "employee",
                "employee.current_branch",
                "employee.current_position"
            ])
            ->orderBy("id","DESC");

            if ($request->has(["export"]) && $request->export) {
                $overtime = $overtime->get();
                return Excel::download(new TravelOvertimeExport($overtime), "overtime.xls");
            }
            
            $overtime = $overtime->paginate($perPage);

            return $this->sendResponse($overtime);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTravelOvertime(Request $request) {
        $request["export"] = true;

        return $this->getAllTravelOvertime($request);
    }

    public function createTravelOvertime(Request $request) {
        try {
            $request->validate([
                "employee_id" => "required",
                "date" => "required",
                "travel_time" => "required",
            ]);

            $employee = Employee::where("employee_number", $request->employee_id)->first();

            if ($employee) {
                TravelOvertime::create([
                    "employee_id" => $employee->id,
                    "date" => $request->date,
                    "travel_time" => $request->travel_time,
                    "remarks" => $request->remarks ?? null
                ]);

                return $this->sendResponse(["message" => "Travel overtime successfully created"]);
            }

            return $this->sendError(["message" => "Employee not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editTravelOvertime(Request $request, $id) {
        try {
            $request->validate([
                "date" => "required",
                "travel_time" => "required",
            ]);

            $travelOvertime = TravelOvertime::find($id);

            if ($travelOvertime) {
                $travelOvertime->update([
                    "date" => $request->date,
                    "travel_time" => $request->travel_time,
                    "remarks" => $request->remarks ?? null,
                ]);

                return $this->sendResponse(["message" => "Travel overtime saved successfully"]);
            }

            return $this->sendError(["message" => "Travel overtime not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

}
