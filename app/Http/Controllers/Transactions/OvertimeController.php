<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\Transactions\OvertimeExport;
use App\Jobs\ProcessOvertimeImport;
use App\Models\Overtime;
use App\Models\Employee;
use Exception;
use DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class OvertimeController extends Controller
{
    public function getAllOvertime(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $overtime = Overtime::query();

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

                $start_time = $year . '-' . $request->cutoff['start_time'];
                $end_time = $year . '-' . $request->cutoff['end_time'];

                $overtime = $overtime->whereBetween("date_filed", [$start_time, $end_time]);
            }

            $overtime = $overtime->with([
                "employee",
                "employee.current_branch",
                "employee.current_position",
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
            $overtime = Overtime::where("status", "approved")->get();
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
                $unique_overtime = Overtime::find($overtime);

                $unique_overtime->update([
                    "status" => "Approved"
                ]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Selected overtime approved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function approveOvertime ($id) {
        try {
            DB::beginTransaction();
            $overtime = Overtime::find($id);

            $overtime->update([
                "status" => "Approved"
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Overtime approved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function unapproveOvertime ($id) {
        try {
            DB::beginTransaction();
            $overtime = Overtime::find($id);

            $overtime->update([
                "status" => "Unapproved"
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Overtime unapproved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editOvertime(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "overtime_date"=> "required",
                "purpose"=> "required",
                "total_hours"=> "required",
                "status"=> "required",
            ]);

            $employee = Employee::where("id", $request->employee_id)->first();

            $overtime = Overtime::find( $request->id);

            $overtime->update([
                "date_filed"=> $request->date_filed,
                "employee_id"=> $employee->id,
                "biometrics_id"=> $employee->biometric_number,
                "overtime_date"=> $request->overtime_date,
                "purpose"=> $request->purpose,
                "total_hours"=> $request->total_hours,
                "status"=> $request->status,
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
                $user = Auth::user();
                activity()->causedBy($user)->useLog("Import Overtime")->log('User imported overtime from cshub.');
                return $this->sendResponse(["message" => "Excel file imported successfully"]);
            }
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}