<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeePositionsExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Position;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Rate;
use Exception;
use DB;
use Maatwebsite\Excel\Facades\Excel;

class EmployeePositionControlller extends Controller
{
    private function getBranchBasedOicDetails(Employee $employee): array
    {
        $hasBranchOicAssignment = Branch::where(function ($query) use ($employee) {
            $query->where("branch_head_employee_number", $employee->employee_number)
                ->orWhere("kitchen_oic_employee_number", $employee->employee_number)
                ->orWhere("kitchen_oic_2_employee_number", $employee->employee_number);
        })->exists();

        $employee->loadMissing("latest_position");

        $oicAssignment = $hasBranchOicAssignment ? "Yes" : "No";
        $dateAppointed = $hasBranchOicAssignment
            ? ($employee->latest_position?->date_appointed ?? now()->toDateString())
            : null;

        return [
            "oic_assignment" => $oicAssignment,
            "date_appointed" => $dateAppointed,
            "oic_status" => $hasBranchOicAssignment ? "OIC" : null,
        ];
    }

    public function getEmployeePositionHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();

            $positions = EmployeePosition::with(["position", "employee"])
                ->where("employee_id", $employee->id)
                ->orderBy("date_started","DESC")
                ->orderBy("id", "DESC");

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
            $branchBasedOic = $this->getBranchBasedOicDetails($employee);

            $employee->update([
                "position_id" => $position->id,
                "station_id" => $position->station_id,
                "oic_status" => $branchBasedOic["oic_status"],
            ]);

            $employee->positions()->attach($request->position_id, [
                "date_started" => $request->date_started,
                "remarks" => $request->remarks,
                "oic_assignment" => $branchBasedOic["oic_assignment"],
                "date_appointed" => $branchBasedOic["date_appointed"],
            ]);

            $salary = $position->salary;
            $payrollList = $employee->payroll_list;

            $monthlyRate = 0;
            $minimum = 0;
            $multiplier = 26;
            if ($employee->schedule->schedule_type->name == "Compressed Schedule") {
                $multiplier = 22;
            }

            if ($salary->rate_category === "MINIMUM") {
                $rate = Rate::where("code", $employee->home_branch->rate_code)->first();
                $minimum = $rate->minimum ?? 0;
                $monthlyRate = $minimum * $multiplier;
            } else {
                $monthlyRate = $salary->basic_salary;
            }

            if ($payrollList) {
                $payrollList->update([
                    "salary_amount" => $monthlyRate
                ]);
            }

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
            $branchBasedOic = $this->getBranchBasedOicDetails($employee);

            $employee->update([
                "position_id" => $request->position_id,
                "oic_status" => $branchBasedOic["oic_status"],
            ]);
            EmployeePosition::where("id", $request->id)->update( [
                ...$request->except(["id", "oic_assignment", "date_appointed"]),
                "oic_assignment" => $branchBasedOic["oic_assignment"],
                "date_appointed" => $branchBasedOic["date_appointed"],
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Position updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
