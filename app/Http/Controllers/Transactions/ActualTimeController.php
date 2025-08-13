<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessActualTimeImport;
use App\Jobs\TransferLogsToTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\BiotimePunch;
use App\Models\Employee;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Str;
use DB;
use Illuminate\Support\Facades\Auth;

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

            if ($request->has(["date_from", "date_to"])) {
                $bioTimeLogs = $bioTimeLogs->whereBetween('date', [$request->date_from, $request->date_to]);
            }

            $bioTimeLogs = $bioTimeLogs->with([
                "employee",
                "punches",
                "punches.branch"
            ])
            ->paginate($perPage);

            return $this->sendResponse($bioTimeLogs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createBiotimeLog(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "punches" => "required",
                "employee_number" => "required",
                "branch_id" => "required",
                "date" => "required"
            ]);

            $user = Auth::user();
            $employee = Employee::where("employee_number", $request->employee_number)->first();

            if ($employee) {
                $isExisting = $employee->attendance()->whereDate('date', $request->date)->first();

                if ($isExisting) {
                    if ($request->has('overwrite') && $request->overwrite) {
                        $isExisting->punches()->delete();
                        $isExisting->delete();
                        
                        $biotime = BiotimeLog::create([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "date" => $request->date,
                            "remarks" => "Overwritten by ".$user->full_name,
                            "is_transferred" => 0
                        ]);
                    } else {
                        DB::rollBack();
                        return $this->sendError(["message" => "Existing"]);
                    }
                } else {
                    $biotime = BiotimeLog::create([
                        "employee_id" => $employee->id,
                        "biometric_number" => $employee->biometric_number,
                        "date" => $request->date,
                        "remarks" => "Added by ".$user->full_name,
                        "is_transferred" => 0
                    ]);
                }

                foreach ($request->punches as $punch) {
                    $biotime->punches()->create([
                        "date" => $biotime->date,
                        "punch_time" => $punch["punch_time"],
                        "remarks" => "Added by ".$user->full_name,
                        "branch_id" => $request->branch_id
                    ]);
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
            ]);

            $user = Auth::user();
            $biotime = BiotimeLog::find($bioTimeID);

            if ($biotime) {
                foreach ($request->punches as $punch) {
                    if (isset($punch["id"])) {
                        $biotime->punches()
                        ->where("id", $punch["id"])
                        ->update([
                            "punch_time" => $punch["punch_time"],
                        ]);
                    } else {
                        $biotime->punches()->create([
                            "date" => $biotime->date,
                            "punch_time" => $punch["punch_time"],
                            "remarks" => "Added by ".$user->full_name
                        ]);
                    }
                }
            }

            DB::commit();
            return $this->sendResponse(["message" => "Biotime punches saved successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
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
                ProcessActualTimeImport::dispatch($filePath);
            }

            return $this->sendResponse(["message" => "Excel file imported successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
