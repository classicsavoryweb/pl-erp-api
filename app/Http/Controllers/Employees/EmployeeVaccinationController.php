<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeVaccine;
use Illuminate\Http\Request;
use Exception;
use DB;

class EmployeeVaccinationController extends Controller
{
    public function getEmployeeVaccines(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $vaccines = EmployeeVaccine::
                with(["vaccine"])
                ->where("employee_id", $employee->id)
                ->orderBy("date","DESC")
                ->paginate($perPage);

            return $this->sendResponse($vaccines);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function addVaccine(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "vaccine_id" => "required",
                "date" => "required",
                "dose" => "required",
                "status" => "required",
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            EmployeeVaccine::create([
                "employee_id" => $employee->id,
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Vaccine added successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editVaccine(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "id" => "required",
                "vaccine_id" => "required",
                "date" => "required",
                "dose" => "required",
                "status" => "required",
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            EmployeeVaccine::where("id", $request->id)->update([
                "employee_id" => $employee->id,
                "vaccine_id" => $request->vaccine_id,
                "date" => $request->date,
                "dose" => $request->dose,
                "status" => $request->status,
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Vaccine updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
