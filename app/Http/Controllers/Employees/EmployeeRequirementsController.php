<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeRequirements;
use Illuminate\Http\Request;
use Exception;
class EmployeeRequirementsController extends Controller
{
    public function getEmployeeRequirements(Request $request, $employee_number) {
        try {
            $requirements = EmployeeRequirements::where("employee_number", $employee_number)->first();

            return $this->sendResponse(["requirements" => $requirements]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function updateOrCreateRequirements(Request $request, $employee_number) {
        try {
            $employee = Employee::where("employee_number", $employee_number)->first();

            if ($employee->requirements()->count() === 1) {
                $employee->requirements()->update([
                    ...$request->except(["created_at", "updated_at"])
                ]);
            } else {
                $employee->requirements()->create([
                    "employee_id" => $employee->id,
                    "employee_number" => $employee->employee_number,
                    ...$request->except(["created_at", "updated_at"])
                ]);
            }

            return $this->sendResponse(["message" => "Requirements saved successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

}
