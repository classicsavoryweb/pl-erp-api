<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeeTrainingsExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeTrainings;
use Illuminate\Http\Request;
use Exception;
use DB;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeTrainingController extends Controller
{
    public function getEmployeeTrainings(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $training = EmployeeTrainings::with("employee")
                ->where("employee_id", $employee->id)
                ->orderBy("date","DESC");

            if($request->has(["export"]) && $request->export) {
                $training = $training->get();
                return Excel::download(new EmployeeTrainingsExport($training), "employee_trainings.xls");
            }

            $training = $training->paginate($perPage);

            return $this->sendResponse($training);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportEmployeeTrainings(Request $request, $employee_number) {
        $request["export"] = true;

        return $this->getEmployeeTrainings($request, $employee_number);
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

    public function editTrainingTitle(Request $request, $employee_number) {
    try {
            DB::beginTransaction();

            $request->validate([
                "id" => "required",
                "others" => "required",
                "speaker" => "required",
                "training_title" => "required",
                "venue" => "required",
                "date" => "required",
                "covered_date" => "required"
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            EmployeeTrainings::where("id", $request->id)->update([
                "employee_id" => $employee->id,
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Training title updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
