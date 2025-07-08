<?php

namespace App\Http\Controllers\Transactions;

use App\Exports\Transactions\ScheduleTimeExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessScheduleTimeImport;
use App\Models\ScheduleTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use DB;
use Exception;
use Str;
use Maatwebsite\Excel\Facades\Excel;

class ScheduleTimeController extends Controller
{
    public function getAllTransactionsScheduleTime(Request $request) {
        try {
            $perPage = $request->perPage ?? 10;

            $schedule_time = ScheduleTime::query();
            
            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $schedule_time = $schedule_time->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $schedule_time = $schedule_time->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $schedule_time = $schedule_time->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["date_from", "date_to"])) {
                $schedule_time = $schedule_time->whereBetween('date', [$request->date_from, $request->date_to]);
            }

            if ($request->has(["export"]) && $request->export) {
                $schedule_time = $schedule_time->get();
                return Excel::download(new ScheduleTimeExport($schedule_time), "schedule_time.xls");
            }

            if ($request->has(['branch_id'])) {
                $schedule_time = $schedule_time->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            $schedule_time = $schedule_time
            ->with(["employee", "employee.current_branch"])
            ->paginate($perPage);

            return $this->sendResponse($schedule_time);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTransactionsScheduleTime(Request $request) {
        $request["export"] = true;

        return $this->getAllTransactionsScheduleTime($request);
    }

    public function getTransactionsScheduleTime(Request $request) {
        try {
            $schedule_time = ScheduleTime::all();
            return $this->sendResponse(["schedule_time" => $schedule_time]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function importTransactionsScheduleTimeExcel (Request $request) {
        try {
            $request->validate([
                "schedule_time_file" => "required"
            ]);

            if ($request->hasFile("schedule_time_file")) {
                $storage = Storage::disk("hr_system_folder");
                $directory = "/imports";
                $file = $request->file("schedule_time_file");
                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));
                
                ProcessScheduleTimeImport::dispatch($filePath);
            }
            return $this->sendResponse(["message" => "Excel file imported successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
