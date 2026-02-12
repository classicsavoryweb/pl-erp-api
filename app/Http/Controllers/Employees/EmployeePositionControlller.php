<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeePositionsExport;
use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeePosition;
use Exception;
use DB;
use Maatwebsite\Excel\Facades\Excel;

class EmployeePositionControlller extends Controller
{
    public function getEmployeePositionHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();

            $positions = EmployeePosition::with(["position", "employee"])
                ->where("employee_id", $employee->id)
                ->orderBy("date_started","DESC");

            if($request->has(["export"]) && $request->export) {
                $positions = $positions->get();
                return Excel::download(new EmployeePositionsExport($positions), "positions.xls");
            }

            $positions = $positions->paginate($perPage);

            return $this->sendResponse($positions);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportEmployeePositionHistory(Request $request, $employee_number) {
        $request["export"] = true;

        return $this->getEmployeePositionHistory($request, $employee_number);
    }
    public function addPosition(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "position_id" => "required",
                "date_started" => "required"
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();
            $position = Position::find($request->position_id);
            $employee->update([
                "position_id" => $position->id,
                "station_id" => $position->station_id,
                "oic_status" => $request->oic_assignment == "Yes" ? "OIC" : null,
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
                "oic_status" => $request->oic_assignment == "Yes" ? "OIC" : null,
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
