<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use DB;

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
            $employeeNumber = "SFI" . str_pad(++$employeeCount, 7, '0', STR_PAD_LEFT);
            $pendingStatus = EmployeeStatus::where('description', "Pending")->first();
            $fields = $request->except(["contract_duration"]);

            $employee = Employee::create([
                "employee_number" => $employeeNumber,
                "employee_status" => $pendingStatus->id,
                ...$fields
            ]);

            $startDate = Carbon::parse($request->date_hired);
            $contractEndDate = $startDate->addMonths($request->contract_duration)->toDateString();
            Contract::create([
                "employee_id" => $employee->id,
                "employee_number" => $employee->employee_number,
                "contract_start_date" => $employee->date_hired,
                "contract_end_date" => $contractEndDate,
                "contract_number" => 1,
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
            $employee = Employee::with(["position", "agency", "branch", "station", "employee_status"])
                ->where("employee_number", $employee_number)
                ->first();

            return $this->sendResponse(["employee" => $employee]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    public function getAllEmployees(Request $request) { 
        try {
            $perPage = $request->per_page ?? 10;

            $employees = Employee::with(["position", "agency", "branch", "station"])
            ->orderBy("date_hired","desc")
            ->paginate($perPage);
            return $this->sendResponse($employees);
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
