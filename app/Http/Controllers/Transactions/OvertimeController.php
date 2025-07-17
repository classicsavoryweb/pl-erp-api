<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\Transactions\OvertimeExport;
use App\Jobs\ProcessOvertimeImport;
use App\Models\BiotimeLog;
use App\Models\TimeKeeping;
use App\Models\ScheduleTime;
use App\Models\Employee;
use Exception;
use DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use Carbon\Carbon;

class OvertimeController extends Controller
{
    public function getAllOvertime(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $overtime = TimeKeeping::query();

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
                        $overtime = $overtime->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $overtime = $overtime->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $overtime = $overtime->where("overtime_status", $request->status);
            }

            if ($request->has(["date_from", "date_to"])) {
                $overtime = $overtime->whereBetween('date', [$request->date_from, $request->date_to]);
            }

            if ($request->has(['branch_id'])) {
                $overtime = $overtime->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            $overtime = $overtime->with([
                "employee",
                "employee.current_branch",
            ])
            ->orderBy("id","DESC");

            if ($request->has(["export"]) && $request->export) {
                $overtime = $overtime->get();
                return Excel::download(new OvertimeExport($overtime), "overtime.xls");
            }
            
            $overtime = $overtime->paginate($perPage);

            return $this->sendResponse($overtime);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllOvertime(Request $request) {
        $request["export"] = true;

        return $this->getAllOvertime($request);
    }

    public function getOvertime(Request $request) {
        try {
            $overtime = TimeKeeping::where("overtime_status", "approved")->get();
            return $this->sendResponse(["overtime" => $overtime]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function approveBulkOvertime(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "overtime_id" => "required"
            ]);

            foreach($request->overtime_id as $overtime) {
                $timekeeping = Timekeeping::find($overtime);

                $timekeeping->update([
                    "overtime_status" => "Approved"
                ]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Selected overtime approved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function approveOvertime (Request $request, $id) {
        try {
            DB::beginTransaction();
            $timekeeping = TimeKeeping::find($id);

            $timekeeping->update([
                "overtime_status" => "Approved"
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Overtime approved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function unapproveOvertime (Request $request, $id) {
        try {
            DB::beginTransaction();
            $timekeeping = TimeKeeping::find($id);

            $timekeeping->update([
                "overtime_status" => "Unapproved"
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Overtime unapproved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    // public function createOvertime(Request $request) {
    //     try {
    //         $request->validate([
    //             "date"=> "required",
    //             "regular_overtime"=> "required",
    //             "night_diff_overtime"=> "required",
    //             "legal_holiday_overtime"=> "required",
    //             "special_holiday_overtime"=> "required",
    //             "overtime_status"=> "required",
    //         ]);

    //         $employee = Employee::where("employee_number", $request->employee_number)->first();

    //         $employee_id = $employee->id;

    //         $biotime_log_id = BiotimeLog::where("employee_id", $employee_id)->first();

    //         $schedule_time_id = ScheduleTime::where("employee_id", $employee_id)->first();

    //         Timekeeping::create([
    //             "employee_id"=> $employee_id,
    //             "biometric_number"=> $employee->biometric_number,
    //             "biotime_log_id"=> $biotime_log_id,
    //             "schedule_time_id"=> $schedule_time_id,
    //             "date"=> $request->date,
    //             "regular_overtime"=> $request->regular_overtime,
    //             "night_diff_overtime"=> $request->nigh_diff_overtime,
    //             "legal_holiday_overtime"=> $request->legal_holiday_overtime,
    //             "special_holiday_overtime"=> $request->special_holiday_overtime,
    //             "overtime_status"=> $request->overtime_status 
    //         ]);

    //     } catch(Exception $e) {
    //         return $this->sendError(["message"=> $e->getMessage()]);
    //     }
    // }

    public function editOvertime(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "date"=> "required",
                "regular_overtime"=> "required",
                "night_diff_overtime"=> "required",
                "legal_holiday_overtime"=> "required",
                "special_holiday_overtime"=> "required",
            ]);

            $employee = Employee::where("id", $request->employee_id)->first();

            $timekeeping = Timekeeping::find( $request->id);

            $timekeeping->update([
                "employee_id"=> $employee->id,
                "date"=> $request->date,
                "regular_overtime"=> $request->regular_overtime,
                "night_diff_overtime"=> $request->night_diff_overtime,
                "legal_holiday_overtime"=> $request->legal_holiday_overtime,
                "special_holiday_overtime"=> $request->special_holiday_overtime,
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Overtime updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function importOvertimeExcel (Request $request) {
        try {
            $request->validate([
                "overtime_file" => "required"
            ]);

            if ($request->hasFile("overtime_file")) {
                $storage = Storage::disk("hr_system_folder");
                $directory = "/imports";
                $file = $request->file("overtime_file");
                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));
                
                ProcessOvertimeImport::dispatch($filePath);
            }
            return $this->sendResponse(["message" => "Excel file imported successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}