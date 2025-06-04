<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeeTrackingExport;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\BranchEmployee;
use App\Models\Contract;
use App\Models\EducationHistory;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmployeeStatus;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use Illuminate\Support\Facades\Storage;
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

            $employeeCount = Employee::count();
            $employeeNumber = str_pad(++$employeeCount, 7, '0', STR_PAD_LEFT);
            $pendingStatus = EmployeeStatus::where('description', "Pending")->first();
            $fields = $request->except(["contract_duration"]);
            $position = Position::find($request->position_id);
            $biometricsNumber = "";

            if ($request->type === "Direct") {
                $biometricsNumber = "10".$employeeNumber;
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
            }

            $employee = Employee::create([
                "employee_number" => $employeeNumber,
                "employee_status_id" => $pendingStatus->id,
                "station_id" => $position->station_id,
                "biometric_number" => $biometricsNumber,
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
                "contracts"
            ])->where("employee_number", $employee_number)
            ->first();
            $employee->age = Carbon::parse($employee->birthday)->age;

            $storage = Storage::disk("hr_system_folder");
            if ($employee->image) {
                $profilePicture = $storage->get($employee->image);       
                $employee->profile_picture = 'data:image/jpeg;base64,' . base64_encode($profilePicture);
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

            if ($request->has(["date_from", "date_to"])) {
                $employees = $employees->whereBetween('date_hired', [$request->date_from, $request->date_to]);
            }

            if ($request->has(["status"])) {
                $employees = $employees->where("status", $request->status);
            } else {
                $employees = $employees->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END");
            }

            $employees = $employees->with(["current_position", "agency", "current_branch", "station"])
            ->orderBy("date_hired", "desc");

            if ($request->has(["export"]) && $request->export) {
                $employees = $employees->get();
                return Excel::download(new EmployeeTrackingExport($employees), "employees.xls");
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
            $activities = Activity::with("causer")
                ->forSubject($employee)
                ->orderBy("created_at", "DESC")
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
                    );
                });
            }

            $employees = $employees->take(100)->get();
            return $this->sendResponse($employees);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function updateEmployeeDetails(Request $request, $employee_number) { 
        try {
            DB::beginTransaction();
            $employee = Employee::where("employee_number", $employee_number)->first();

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

            $pathName = "";
            if ($request->hasFile("image")) {
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
                "status" => $request->status
            ]);

            if (!empty($request->dependents) && count($request->dependents) > 0) {
                foreach($request->dependents as $dependant) {
                    $employee->dependents()->updateOrCreate([
                        "employee_number" => $employee->employee_number,
                        ...$dependant
                    ]);
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
