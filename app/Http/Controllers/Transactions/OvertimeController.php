<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Services\TimeKeepingService;
use App\Models\AdjustmentTimeKeeping;
use App\Models\TimeKeepingPayment;
use Illuminate\Http\Request;
use App\Exports\Transactions\OvertimeExport;
use App\Jobs\FetchOvertimeJob;
use App\Jobs\ProcessOvertimeImport;
use App\Models\Adjustment;
use App\Models\AdjustmentPunch;
use App\Models\BiotimeLog;
use App\Models\Overtime;
use App\Models\Employee;
use App\Models\TimeKeeping;
use Exception;
use DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Branch;

class OvertimeController extends Controller
{
    protected $timeKeepingService;

    public function __construct(){
        $this->timeKeepingService = new TimeKeepingService();
    }

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
                $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $year);
                $start_time = $dates["start_date"];
                $end_time = $dates["end_date"];

                $overtime = $overtime->whereBetween("overtime_date", [$start_time, $end_time]);
            }

            $overtime = $overtime->with([
                "employee",
                "employee.current_branch",
                "employee.current_position",
                "timekeeping",
                "timekeeping.schedule",
                "adjustment_timekeeping",
                "adjustment_timekeeping.schedule"
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

    public function fetchOvertime(Request $request) {
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

                FetchOvertimeJob::dispatch($branch_id, Auth::id(), $context);
            }

            return $this->sendResponse(["message" => "Overtime is fetching"]);
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

    public function voidOvertime($id) {
        try {
            DB::beginTransaction();
            $overtime = Overtime::find($id);

            $overtime->update([
                "status" => "Voided"
            ]);

            $overtimeLink = env('CS_HUB_URL_LINK')."/update-overtime/".$overtime->cshub_overtime_id;
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');

            Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])->timeout(60)
            ->retry(50, 1000, function ($exception, $attempt) {
                return str_contains($exception->getMessage(), 'cURL error 60');
            })
            ->post($overtimeLink,[
                "status_payroll" => "unpaid",
                "status" => "rejected",
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Overtime voided successfully"]);
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
                "biometric_number"=> $employee->biometric_number,
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

    public function forceAdjustOvertime (Request $request, $id) {
        $overtime = Overtime::find($id);
        $employee = $overtime->employee;

        try {
            DB::beginTransaction();
            $timeKeeping = TimeKeeping::where("employee_id", $employee->id)
            ->where("date", $overtime->overtime_date)
            ->first();

            if ($timeKeeping) {
                $schedule = $timeKeeping->schedule;
                $oldtimeKeepingPayment = TimeKeepingPayment::where("timekeeping_id", $timeKeeping->id)->first();
                $log = BiotimeLog::where("employee_id", $employee->id)
                ->where("date", $timeKeeping->date)
                ->first();

                $adjustedTimeKeeping = $this->timeKeepingService->computeTimeKeeping(
                    $timeKeeping->branch_id,
                    $log,
                    $log->punches,
                    $schedule->timetable,
                    $employee,
                    new AdjustmentTimeKeeping(),
                    true,
                    "force adjustment"
                );

                $adjustedTimeKeepingPayment = $this->timeKeepingService->computePayment(
                    $adjustedTimeKeeping,
                    $schedule->timetable,
                    true
                );

                $taxable_amount = $adjustedTimeKeepingPayment->total_amount - ($oldtimeKeepingPayment->total_amount ?? 0);
                $amount_paid = $oldtimeKeepingPayment->total_amount ?? 0;
                $adjustment = Adjustment::create([
                    "employee_id" => $employee->id,
                    "biometric_number" => $employee->biometric_number,
                    "date" => $timeKeeping->date,
                    "days" => 0,
                    "cancelled_rd" => 0,
                    "taxable_amount" => round($taxable_amount, 2) ,
                    "amount_paid" => round($amount_paid, 2),
                    "correct_amount_paid" => round($adjustedTimeKeepingPayment->total_amount, 2),
                    "actual_schedule" => $schedule->timetable,
                    "type" => "Overtime Force Adjustments",
                    "status" => "Approved",
                    "added_by" => Auth::user()->id,
                    "added_at" => now(),
                ]);

                foreach($log->punches as $punch) {
                    AdjustmentPunch::create([
                        "adjustment_id" => $adjustment->id,
                        "punch_time" => $punch->punch_time,
                        "date" => $timeKeeping->date,
                        "branch_id" => $punch->branch_id ?? null
                    ]);
                }

                DB::commit();
                return $this->sendResponse(["message" => "Overtime adjustment created successfully"]);
            }

            $cutOff = $this->getCutOffForDate($overtime->overtime_date);
            $timeKeepings = TimeKeeping::where("employee_id", $employee->id)
            ->whereBetween("date", [$cutOff['start_time'], $cutOff['end_time']])
            ->where("approved_overtime", 0)
            ->where("regular_overtime", ">" , 0)
            ->get();

            return $this->sendError([
                "message" => "not_found",
                "timekeepings" => $timeKeepings
            ]);

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
                
                ProcessOvertimeImport::dispatch($filePath, Auth::id());
                $user = Auth::user();
                activity()->causedBy($user)->useLog("Import Overtime")->log('User imported overtime from cshub.');
                return $this->sendResponse(["message" => "Excel file imported successfully"]);
            }
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
