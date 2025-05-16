<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeePosition;
use Exception;
use DB;

class EmployeePositionControlller extends Controller
{
    public function getEmployeePositionHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $positions = EmployeePosition::
                with(["position"])
                ->where("employee_id", $employee->id)
                ->orderBy("date_started","DESC")
                ->paginate($perPage);

            return $this->sendResponse($positions);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    public function addPosition(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "position_id" => "required",
                "date_started" => "required"
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->update([
                "position_id" => $request->position_id,
            ]);
            $employee->positions()->attach($request->position_id, [
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Position added successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editPosition(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "id" => "required",
                "position_id" => "required",
                "date_started" => "required"
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->update([
                "position_id" => $request->position_id,
            ]);
            EmployeePosition::where("id", $request->id)->update( [
                ...$request->except(["id"])
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Position updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
