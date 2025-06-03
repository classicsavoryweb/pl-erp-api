<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeTrainings;
use Illuminate\Http\Request;
use Exception;
use DB;

class EmployeeTrainingController extends Controller
{
    public function getEmployeeTrainings(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $training = EmployeeTrainings::where("employee_id", $employee->id)
                ->orderBy("date","DESC")
                ->paginate($perPage);

            return $this->sendResponse($training);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

     public function addTrainingTitle(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "others" => "required",
                "speaker" => "required",
                "training_title" => "required",
                "venue" => "required",
                "date" => "required",
                "covered_date" => "required"
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            EmployeeTrainings::create([
                "employee_id" => $employee->id,
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Training title added successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
