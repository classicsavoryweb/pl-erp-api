<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeHealthSafety;
use Illuminate\Http\Request;
use Exception;
use DB;

class EmployeeHealthSafetyController extends Controller
{
    public function getEmployeeHealthSafety(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $records = EmployeeHealthSafety::where("employee_id", $employee->id)
            ->with("diagnosis")
            ->paginate($perPage);

            return $this->sendResponse($records);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function addEmployeeHealthSafety(Request $request, $employee_number) {
        try {
            $request->validate([
                "consultation_date" => "required",
                "hso" => "required",
                "attending_physician" => "required",
                "physician_name" => "required",
                "chief_complaint" => "required"
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            EmployeeHealthSafety::create([
                "employee_id" => $employee->id,
                ...$request->all()
            ]);

            return $this->sendResponse(["message" => "Record added successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editEmployeeHealthSafety(Request $request, $employee_number) {
        try {
            $request->validate([
                "id" => "required",
                "consultation_date" => "required",
                "hso" => "required",
                "attending_physician" => "required",
                "physician_name" => "required",
                "chief_complaint" => "required"
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();

            EmployeeHealthSafety::where("id", $request->id)
            ->update([
                "employee_id" => $employee->id,
                ...$request->except("id")
            ]);

            return $this->sendResponse(["message" => "Record updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
