<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BranchEmployee;
use App\Models\Employee;
use Exception;
use DB;

class EmployeeBranchControlller extends Controller
{
    public function getEmployeeBranchHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $branches = BranchEmployee::
                with(["branch"])
                ->where("employee_id", $employee->id)
                ->orderBy("date_assigned","DESC")
                ->paginate($perPage);

            return $this->sendResponse($branches);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    public function addBranch(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "branch_id" => "required",
                "date_assigned" => "required"
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->update([
                "branch_id" => $request->branch_id,
            ]);
            $employee->branches()->attach($request->branch_id, [
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Branch added successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editBranch(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "id" => "required",
                "branch_id" => "required",
                "date_assigned" => "required"
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->update([
                "branch_id" => $request->branch_id,
            ]);
            BranchEmployee::where("id", $request->id)->update( [
                ...$request->except(["id"])
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Branch updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
