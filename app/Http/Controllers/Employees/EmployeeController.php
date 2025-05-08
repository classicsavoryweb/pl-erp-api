<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\BranchEmployee;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmployeeStatus;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use Spatie\Activitylog\Models\Activity;

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

            $employee = Employee::create([
                "employee_number" => $employeeNumber,
                "employee_status_id" => $pendingStatus->id,
                "station_id" => $position->station_id,
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
                    "positions"
                ])->where("employee_number", $employee_number)
                ->first();

            return $this->sendResponse(["employee" => $employee]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    public function getAllEmployees(Request $request) { 
        try {
            $perPage = $request->per_page ?? 10;

            $employees = Employee::with(["current_position", "agency", "current_branch", "station"])
            ->orderBy("date_hired","desc")
            ->paginate($perPage);
            return $this->sendResponse($employees);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
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
}
