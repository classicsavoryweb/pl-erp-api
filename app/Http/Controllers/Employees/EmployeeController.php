<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeeTrackingExport;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\Contract;
use App\Models\EducationHistory;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmployeeStatus;
use App\Models\OtherCompany\OtherEmployee;
use App\Models\Position;
use App\Models\Region;
use App\Models\ScheduleDetail;
use App\Models\ScheduleType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Str;

class EmployeeController extends Controller
{
    public function addEmployee(Request $request){
        try {
            DB::beginTransaction();
            $request->validate([
                "type" => "required",
                "first_name" => "required",
                "last_name" => "required",
                "gender" => "required",
                "position_id" => "required",
                "branch_id" => "required",
                "home_branch_id" => "required",
                "date_hired" => "required",
                "birthday" => "required",
                "contact_number" => "required",
                "contract_duration" => "required",
            ]);
            
            $duplicate = Employee::where('first_name', $request->first_name)
                ->where('last_name', $request->last_name)
                ->where('gender', $request->gender)
                ->where('position_id', $request->position_id)
                ->where('status', 'active') 
                ->first();

            if ($duplicate) {
                return $this->sendError([
                    "message" => "Employee already exists"
                ]);
            }

            $inc = 1;
            do {
                $thisCompanyEmployeeCount = Employee::count();
                $otherCompanyEmployeeCount = OtherEmployee::count();
                $employeeCount = $thisCompanyEmployeeCount + $otherCompanyEmployeeCount;
                $company = env('COMPANY_NAME');
                $employeeNumber = str_pad($employeeCount + $inc, 7, '0', STR_PAD_LEFT);
                $employeeNumber2 = $company."-".str_pad($employeeNumber, 7, '0', STR_PAD_LEFT);
                $inc++;
            } while (
                Employee::whereIn("employee_number", [$employeeNumber, "A".$employeeNumber])->get()->count() > 0 ||
                OtherEmployee::whereIn("employee_number", [$employeeNumber, "A".$employeeNumber])->get()->count() > 0
            );

            $pendingStatus = EmployeeStatus::where('description', "Pending")->first();
            $fields = $request->except(["contract_duration", "flexible_schedule", "schedule_type", "incentive_leave", "region"]);
            $position = Position::find($request->position_id);
            $biometricsNumber = "";

            if ($request->type === "Direct") {
                $prefix = env('COMPANY_NAME') == "SFI" ? "10" : "30";

                $biometricsNumber = $prefix . $employeeNumber;
            }
            if ($request->type === "Agency") {
                $agency = Agency::find($request->agency_id);

                if ($agency->name === "MSC") {
                    $biometricsNumber = "50".$employeeNumber;
                } else if ($agency->name === "GMI") {
                    $biometricsNumber = "70".$employeeNumber;
                } else if ($agency->name === "CNT") {
                    $biometricsNumber = "11".$employeeNumber;
                } else {
                    $biometricsNumber = "12".$employeeNumber;
                }
                $employeeNumber = "A".$employeeNumber;
                $employeeNumber2 = $company."-".$agency->name."-".str_pad($employeeNumber, 7, '0', STR_PAD_LEFT);
            }

            $region = null;
            if ($request->has("region")) {
                $region = Region::where("name", $request->region)->value("description") ?? $request->region;
            }

            $employee = Employee::create([
                "employee_number" => $employeeNumber,
                "employee_number_2" => $employeeNumber2,
                "employee_status_id" => $pendingStatus->id,
                "station_id" => $position->station_id,
                "biometric_number" => $biometricsNumber,
                "region" => $region,
                ...$fields
            ]);

            $startDate = Carbon::parse($request->date_hired);
            $contractEndDate = $startDate->addMonths($request->contract_duration)->toDateString();

            BranchEmployee::create([
                "employee_id" => $employee->id,
                "branch_id" => $request->branch_id,
                "date_assigned" => $request->date_hired
            ]);
            EmployeePosition::create([
                "employee_id" => $employee->id,
                "position_id" => $request->position_id,
                "date_started" => $request->date_hired
            ]);
            Contract::create([
                "employee_id" => $employee->id,
                "employee_number" => $employee->employee_number,
                "contract_start_date" => $employee->date_hired,
                "contract_end_date" => $contractEndDate,
                "contract_number" => "1st",
                "remarks" => "1st contract",
                "status" => "active"
            ]);

            if ($request->type === "Direct") {
                $scheduleType = ScheduleType::where("name", $request->schedule_type)->first();
                ScheduleDetail::create([
                    "employee_id" => $employee->id,
                    "incentive_leave" => $request->incentive_leave,
                    "schedule_type_id" => $scheduleType->id,
                    "flexible_schedule" => $request->flexible_schedule
                ]);
            }
            
            DB::commit();
            return $this->sendResponse(["message" => "Employee added successfully." ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    
    public function getEmployee(Request $request, $employee_number) {
        try {
            $employee = Employee::with([
                "current_position",
                "agency",
                "current_branch",
                "station",
                "employee_status",
                "home_branch",
                "branches",
                "positions",
                "education",
                "dependents",
                "contracts",
                "schedule",
                "schedule.schedule_type",
                "separation"
            ])->where("employee_number", $employee_number)
            ->first();

            $employee->age = Carbon::parse($employee->birthday)->age;
            $employee->schedule_type = $employee->schedule->schedule_type->name ?? "";
            $employee->incentive_leave = $employee->schedule->incentive_leave ?? "";
            $employee->flexible_schedule = $employee->schedule->flexible_schedule ?? "";
            $employee->basic_salary = $employee->monthlySalary();
            $employee->daily_rate = $employee->schedule ? $employee->dailySalary() : 0;

            try {
                $storage = Storage::disk("hr_system_folder");

                if ($employee->image && $storage->exists($employee->image)) {
                    $profilePicture = $storage->get($employee->image);       
                    $employee->profile_picture = 'data:image/jpeg;base64,' . base64_encode($profilePicture);
                }
            } catch (\Throwable $e) {
                Log::info($e->getMessage());
            }

            return $this->sendResponse(["employee" => $employee]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    public function getAllEmployees(Request $request) { 
        try {
            $perPage = $request->per_page ?? 10;

            $employees = Employee::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if ($column == "full_name") {
                    $employees = $employees->where(function ($query) use ($keyword) {
                        $query->whereAny(
                            ["first_name", "last_name", "middle_name"],
                            'LIKE',
                            "%$keyword%"
                        )->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            }
                        )->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                            }
                        );
                    });
                } else {
                    if (Str::contains($column, '.')) {
                        [$relation, $relatedColumn] = explode('.', $column, 2);

                        $employees = $employees->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    } else {
                        $employees = $employees->where($column, 'LIKE', "%$keyword%");
                    }
                }
            }

            if ($request->has(["sortColumn", "sort"])) {
                if ($request->sortColumn === "full_name") {
                    $employees = $employees->orderBy("last_name", $request->sort);
                } else if ($request->sortColumn === "employee_status.description") {
                    $employees = $employees->orderBy(
                        EmployeeStatus::select('description')
                        ->from('employee_status')
                        ->whereColumn('employee_status.id', 'employees.employee_status_id')
                        ->limit(1),
                        $request->sort
                    );
                } else if ($request->sortColumn === "current_branch.name") {
                    $employees = $employees->orderBy(
                        Branch::select('name')
                        ->from('branches')
                        ->whereColumn('branches.id', 'employees.branch_id')
                        ->limit(1),
                        $request->sort
                    );
                } else if ($request->sortColumn === "current_position.description") {
                    $employees = $employees->orderBy(
                        Position::select('description')
                        ->from('positions')
                        ->whereColumn('positions.id', 'employees.position_id')
                        ->limit(1),
                        $request->sort
                    );
                } else {
                    $employees = $employees->orderBy($request->sortColumn, $request->sort);
                }
            }

            if ($request->has(["branch_id"])) {
                $employees = $employees->where("branch_id", $request->branch_id);
            }

            if ($request->has(["position_id"])) {
                $employees = $employees->where("position_id", $request->position_id);
            }

            if ($request->has(["employee_status_id"])) {
                $employees = $employees->where("employee_status_id", $request->employee_status_id);
            }

            if ($request->has(["type"])) {
                $employees = $employees->where("type", $request->type);
            }

            if ($request->filled("agency_id")) {
                $employees = $employees->where("agency_id", $request->agency_id);
            }

            if ($request->has(["date_from", "date_to"])) {
                $employees = $employees->whereBetween('date_hired', [$request->date_from, $request->date_to]);
            }

            if ($request->has(["status"])) {
                $employees = $employees->where("status", $request->status);
            } else {
                $employees = $employees->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END");
            }

            $employees = $employees->with([
                "current_position",
                "latest_position",
                "agency",
                "current_branch",
                "home_branch",
                "station",
                "employee_status",
                "separation",
                "latest_bank_details",
                "schedule",
                "schedule.schedule_type"
            ])
            ->orderBy("date_hired", "desc");

            if ($request->has(["export"]) && $request->export) {
                $employees = $employees->get();
                $headers = collect($request->fields)->pluck('label')->toArray();
                $columns = collect($request->fields)->pluck('value')->toArray();
                return Excel::download(new EmployeeTrackingExport($employees, $headers, $columns), "employees.xls");
            }

            $employees = $employees->paginate($perPage);

            return $this->sendResponse($employees);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportEmployee (Request $request) {
        $request["export"] = true;

        return $this->getAllEmployees($request);
    }

    public function getEmployeeAuditLogs(Request $request, $employeeNumber) {
        try {
            $perPage = $request->per_page ??10;

            $employee = Employee::where("employee_number", $employeeNumber)->first();
            $activities = Activity::with('causer')
            ->where(function ($query) use ($employee) {
                $query->where(function ($q) use ($employee) {
                    $q->where('subject_type', Employee::class)
                    ->where('subject_id', $employee->id);
                })->orWhere('properties->attributes->employee_id', $employee->id);
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage);

            return $this->sendResponse($activities);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getEmployees(Request $request) {
        try {

            $employees = Employee::where("status", "active")->get();
            return $this->sendResponse(["employees" => $employees]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getBranchEmployees(Request $request) {
        try {
            $employees = Employee::where("status", "active")->get();
            $otherEmployees = collect();
            try {
                $otherEmployees = OtherEmployee::where("status", "active")->get();
            } catch (Exception $e) {
                $otherEmployees = collect();
            }

            $employees = $employees->concat($otherEmployees)->values();

            return $this->sendResponse(["employees" => $employees]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getEmployeeList(Request $request) {
        try {

            $employees = Employee::query();
            
            if ($request->has("keyWord")) {
                $keyword = $request->keyWord;

                $employees = $employees->where(function ($query) use ($keyword) {
                    $query->whereAny(
                        ["first_name", "last_name", "middle_name"],
                        'LIKE',
                        "%$keyword%"
                    )->orWhere(function ($query) use ($keyword) {
                            $query->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                        }
                    )->orWhere(function ($query) use ($keyword) {
                            $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                        }
                    );
                });
            }

            if ($request->has("type")) {
                if (in_array($request->type, ["Direct", "Agency"])) {
                    $employees = $employees->where("type", $request->type);
                }
            }

            if ($request->has("rank")) {
                if (in_array($request->rank, ["management", "rank-file"])) {
                    $rank = $request->rank == "management"
                        ? 2
                        : 1;

                    $employees = $employees->whereHas("current_position", function ($query) use ($rank) {
                        $query->where("rank", $rank);
                    });
                }
            }

            if ($request->has("status")) {
                $status = $request->status;
                switch ($status) {
                    case "active":
                        $employees = $employees->where("status", "active");
                        break;
                    case "inactive":
                        $employees = $employees->where("status", "inactive");
                        break; 
                    default:
                        break;
                }
            }

            $employees = $employees->with([
                "current_position",
                "current_branch",
                "employee_status",
                "current_position.station",
            ])
            ->take(100)->get();
            return $this->sendResponse($employees);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getEmployeesWithoutPayrollList(Request $request) {
        try {

            $employees = Employee::query();

            if ($request->has("keyWord")) {
                $keyword = $request->keyWord;

                $employees = $employees->where(function ($query) use ($keyword) {
                    $query->whereAny(
                        ["first_name", "last_name", "middle_name"],
                        'LIKE',
                        "%$keyword%"
                    )->orWhere(function ($query) use ($keyword) {
                            $query->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                        }
                    )->orWhere(function ($query) use ($keyword) {
                            $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                        }
                    );
                });
            }

            if ($request->has("status")) {
                $status = $request->status;
                switch ($status) {
                    case "active":
                        $employees = $employees->where("status", "active");
                        break;
                    case "inactive":
                        $employees = $employees->where("status", "inactive");
                        break; 
                    default:
                        break;
                }
            }

            $employees = $employees->with([
                "current_position",
                "current_branch",
                "employee_status",
                "current_position.station",
            ])
            ->where("type", "Direct")
            ->whereDoesntHave("payroll_list")
            ->orderBy("last_name", "ASC")
            ->get();
            return $this->sendResponse($employees);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }


    public function updateEmployeeDetails(Request $request, $employee_number) { 
        try {
            DB::beginTransaction();
            $employee = Employee::where("employee_number", $employee_number)->first();

            $request->merge(['email' => $request->email ? trim($request->email) : null]);
            $request->validate([
                "email" => [
                    "nullable",
                    "string",
                    "email",
                    "max:255",
                    "regex:/^\S+$/",
                    Rule::unique("employees", "email")->ignore($employee->id),
                ]
            ]);

            $educationData = collect($request->education)
            ->except(["created_at", "updated_at"])
            ->toArray();

            if ($employee->education) {
                $employee->education()->update($educationData);
                $education = $employee->education;
            } else {
                $educationData['employee_id'] = $employee->id;
                $educationData['employee_number'] = $employee->employee_number;
                $education = EducationHistory::create($educationData);
            }

            $scheduleType = ScheduleType::where("name", $request->schedule_type)->first();

            if ($employee->schedule) {
                $schedule = ScheduleDetail::where("employee_id",$employee->id)->first();
                $schedule->update([
                    "schedule_type_id" => $scheduleType->id,
                    "flexible_schedule" => $request->flexible_schedule,
                    "incentive_leave" => $request->incentive_leave
                ]);
            } else {
                ScheduleDetail::create([
                    "employee_id" => $employee->id,
                    "schedule_type_id" => $scheduleType->id,
                    "flexible_schedule" => $request->flexible_schedule,
                    "incentive_leave" => $request->incentive_leave
                ]);
            }

            $pathName = "";

            if ($employee->image) {
                $pathName = $employee->image;
            }

            if ($request->hasFile("image") && $request->file('image')->isValid()) {
                $file = $request->file("image");

                $storage = Storage::disk("hr_system_folder");
                $directory = "/Images/" . $employee->employee_number;
                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }
                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));
                $pathName = $filePath;
            }

            $employee->update([
                "image" => $pathName,
                "first_name" => $request->first_name,
                "middle_name" => $request->middle_name,
                "last_name" => $request->last_name,
                "email" => $request->email,
                "civil_status" => $request->civil_status,
                "gender" => $request->gender,
                "height" => $request->height,
                "birthday" => $request->birthday,
                "birthplace" => $request->birthplace,
                "address" => $request->address,
                "provincial_address" => $request->provincial_address,
                "region" => $request->region,
                "contact_number" => $request->contact_number,
                "tax_type" => $request->tax_type,
                "tin" => $request->tin,
                "sss" => $request->sss,
                "philhealth" => $request->philhealth,
                "pagibig" => $request->pagibig,
                "referred_by" => $request->referred_by,
                "date_hired" => $request->date_hired,
                "remarks" => $request->remarks,
                "regularization_date" => $request->regularization_date,
                "separation_date" => $request->separation_date,
                "separation_reason" => $request->separation_reason,
                "emergency_contact_name" => $request->emergency_contact_name,
                "emergency_contact_number" => $request->emergency_contact_number,
                "emergency_contact_address" => $request->emergency_contact_address,
                "emergency_contact_relationship" => $request->emergency_contact_relationship,
                "education_history_id" => $education->id,
                "employee_status_id" => $request->employee_status_id,
                "status" => $request->status,
                "home_branch_id" => $request->home_branch_id
            ]);

            if (!empty($request->dependents) && count($request->dependents) > 0) {
                foreach($request->dependents as $dependant) {
                    if ( isset($dependant["id"]) ) {
                        $employee->dependents()
                        ->where("id", $dependant["id"])
                        ->update([
                            "employee_number" => $employee->employee_number,
                            "employee_id" => $employee->id,
                            "dependent_name" => $dependant["dependent_name"],
                            "birthday" => $dependant["birthday"],
                        ]);
                    } else {
                        $employee->dependents()->create([
                            "employee_number" => $employee->employee_number,    
                            "employee_id" => $employee->id,
                            "dependent_name" => $dependant["dependent_name"],
                            "birthday" => $dependant["birthday"],
                        ]);
                    }
                }
            }

            DB::commit();
            return $this->sendResponse(["message" => "Employee updated successfully"]);
        } catch (Exception $e) {

            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

}
