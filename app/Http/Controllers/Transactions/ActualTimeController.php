<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessActualTimeImport;
use App\Models\BiotimeLog;
use App\Models\BiotimePunch;
use App\Models\Employee;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Exports\Transactions\ActualLogsExport;
use App\Jobs\FetchBranchLogs;
use App\Models\Branch;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class ActualTimeController extends Controller
{
    public function getAllBiotimeTransactions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $bioTimeLogs = BiotimeLog::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $bioTimeLogs = $bioTimeLogs->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $bioTimeLogs = $bioTimeLogs->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    if ($column === "full_name") {
                        $bioTimeLogs = $bioTimeLogs->whereHas("employee", function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $bioTimeLogs = $bioTimeLogs->where($column, 'LIKE', "%$keyword%");
                    }
                }
            }

            if ($request->has(['branch_id'])) {
                $bioTimeLogs = $bioTimeLogs->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has('cutoff') && $request->filled('cutoff.start_time') && $request->filled('cutoff.end_time')) {
                $year = $request->year ?? Carbon::now()->year;
                $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $year);
                $start_time = $dates["start_date"];
                $end_time = $dates["end_date"];

                $bioTimeLogs = $bioTimeLogs->whereBetween("date", [$start_time, $end_time]);
            }

            if ($request->has(["export"]) && $request->export) {
                $bioTimeLogs = $bioTimeLogs->get();
                return Excel::download(new ActualLogsExport($bioTimeLogs), "actual_logs.xls");
            }

            $bioTimeLogs = $bioTimeLogs->with([
                "employee",
                "employee.current_branch",
                "punches",
                "punches.branch"
            ])
            ->paginate($perPage);

            return $this->sendResponse($bioTimeLogs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllTransactionsActualLogs(Request $request) {
        $request["export"] = true;

        return $this->getAllBiotimeTransactions($request);
    }

    public function createBiotimeLog(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "time_keeping" => "required",
                "employee_number" => "required",
            ]);

            $user = Auth::user();
            $employee = Employee::where("employee_number", $request->employee_number)->first();

            if ($employee) {
                foreach ($request->time_keeping as $timekeeping) {
                    $isExisting = $employee->attendance()->where('date', $timekeeping["date"])->first();

                    if ($isExisting) {
                        $isExisting->punches()->delete();
                        $isExisting->delete();
                    }

                    $biotime =  BiotimeLog::create([
                        "employee_id" => $employee->id,
                        "biometric_number" => $employee->biometric_number,
                        "date" => $timekeeping["date"],
                        "remarks" => "Added by ".$user->full_name,
                        "is_transferred" => 0
                    ]);

                    foreach ($timekeeping["punches"] as $punch) {
                        $biotime->punches()->create([
                            "date" => $biotime->date,
                            "punch_time" => $punch["punch_time"],
                            "remarks" => "Added by ".$user->full_name,
                            "branch_id" => $timekeeping["branch_id"]
                        ]);
                    }
                }

                
            }
            DB::commit();
            return $this->sendResponse(["message" => "Biotime logs saved successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editBiotimePunch(Request $request, $bioTimeID) {
        try {
            DB::beginTransaction();
            $request->validate([
                "punches" => "required",
                "deleted_punch_ids" => "array",
                "deleted_punch_ids.*" => "integer",
            ]);

            $user = Auth::user();
            $biotime = BiotimeLog::find($bioTimeID);

            if ($biotime) {
                $deletedIds = $request->input("deleted_punch_ids", []);
                $didModify = false;
                if (is_array($deletedIds) && count($deletedIds) > 0) {
                    $deletedPunches = $biotime->punches()
                        ->with("branch")
                        ->whereIn("id", $deletedIds)
                        ->get();

                    foreach ($deletedPunches as $punch) {
                        activity()
                            ->causedBy($user)
                            ->performedOn($biotime)
                            ->useLog("Biotime Punch Deletion")
                            ->log("User deleted biotime punch");
                    }
                    $biotime->punches()->whereIn("id", $deletedIds)->delete();
                    $didModify = true;
                }

                foreach ($request->punches as $punch) {
                    if (isset($punch["id"])) {
                        $origPunch = BiotimePunch::find($punch["id"]);

                        if ($origPunch->punch_time !== $punch["punch_time"]) {
                            $biotime->punches()
                            ->where("id", $punch["id"])
                            ->update([
                                "punch_time" => $punch["punch_time"],
                                "branch_id" => $punch["branch_id"] ?? null,
                                "remarks" => "Edited by ". $user->full_name." Original log ".$origPunch->punch_time
                            ]);
                            $didModify = true;
                        } else {
                            $branchId = $punch["branch_id"] ?? null;
                            $biotime->punches()
                            ->where("id", $punch["id"])
                            ->update([
                                "punch_time" => $punch["punch_time"],
                                "branch_id" => $branchId,
                            ]);
                            if ($origPunch->branch_id !== $branchId) {
                                $didModify = true;
                            }
                        }
                    } else {
                        $biotime->punches()->create([
                            "date" => $biotime->date,
                            "punch_time" => $punch["punch_time"],
                            "branch_id" => $punch["branch_id"] ?? null,
                            "remarks" => "Added by ".$user->full_name
                        ]);
                        $didModify = true;
                    }
                }

                if ($didModify) {
                    $biotime->is_modified = true;
                    $biotime->save();
                }
            }

            DB::commit();
            return $this->sendResponse(["message" => "Biotime punches saved successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function deleteBiotimeLog(Request $request, $bioTimeID) {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $biotime = BiotimeLog::with("punches")->find($bioTimeID);

            if (!$biotime) {
                return $this->sendError(["message" => "Biotime log not found"]);
            }

            activity()
                ->causedBy($user)
                ->performedOn($biotime)
                ->useLog("Biotime Log Deletion")
                ->log("User deleted biotime log");

            $biotime->punches()->delete();
            $biotime->delete();

            DB::commit();
            return $this->sendResponse(["message" => "Biotime log deleted successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getBiotimePunchDeletionLogs(Request $request, $bioTimeID) {
        try {
            $activities = Activity::with('causer')
                ->where("log_name", "Biotime Punch Deletion")
                ->where("subject_type", BiotimeLog::class)
                ->where("subject_id", $bioTimeID)
                ->orderBy("created_at", "desc")
                ->get();

            return $this->sendResponse($activities);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function importTransactionsActualTimeLogs(Request $request) {
        try {
            $request->validate([
                "actual_log_file" => "required",
            ]);

            $filePath = null;

            if ($request->hasFile("actual_log_file")) {
                $storage = Storage::disk("hr_system_folder");
                $directory = "/imports";
                $file = $request->file("actual_log_file");

                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));

            } elseif ($request->has("file_path")) {
                $filePath = $request->file_path;
            }

            if ($filePath) {
                ProcessActualTimeImport::dispatch($filePath, Auth::id());
                $user = Auth::user();
                activity()->causedBy($user)->useLog("Import Actual Logs")->log('User imported actual logs');
            }

            return $this->sendResponse(["message" => "Excel file imported successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function fetchBiotimeLogs(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required|array"
            ]);

            $branches = Branch::get()
            ->pluck('name', 'id')
            ->toArray();

            foreach ($request->branch_id as $branch_id) {
                $branch = $branches[$branch_id];
                $context = $branch ?? "Unknown Branch";
    
                FetchBranchLogs::dispatch($branch_id, Auth::id(), $context);
            }

            return $this->sendResponse(["message" => "Biotime logs are now fetching"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
