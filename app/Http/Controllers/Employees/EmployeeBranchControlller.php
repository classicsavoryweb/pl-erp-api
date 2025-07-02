<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeeBranchesExport;
use App\Http\Controllers\Controller;
use App\Models\ActingCapacity;
use App\Models\Branch;
use Illuminate\Http\Request;
use App\Models\BranchEmployee;
use App\Models\Employee;
use Exception;
use DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeBranchControlller extends Controller
{
    public function getEmployeeBranchHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $branches = BranchEmployee::with(["branch", "employee"])
                ->where("employee_id", $employee->id)
                ->orderBy("date_assigned","DESC");

            if($request->has(["export"]) && $request->export) {
                $branches = $branches->get();
                return Excel::download(new EmployeeBranchesExport($branches), "branches.xls");
            }

            $branches = $branches->paginate($perPage);

            return $this->sendResponse($branches);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportEmployeeBranchHistory(Request $request, $employee_number) {
        $request["export"] = true;

        return $this->getEmployeeBranchHistory($request, $employee_number);
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

            $latestActingCapacity = ActingCapacity::where("employee_id", $employee->id)
            ->where("status", "Pending")
            ->latest();

            if ($latestActingCapacity) {
                $latestActingCapacity->update([
                    "branch_id" => $request->branch_id
                ]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Branch added successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function deleteEmployeeBranch(Request $request, $id) {
        try {
            DB::beginTransaction();

            $branch = BranchEmployee::find($id);
            $employee = Employee::find($branch->employee_id);
            $fetchedBranch = Branch::find($branch->branch_id);

            activity()
            ->useLog('Employee Branch Assignment')
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->withProperties([
                "attributes" => [
                    "branch_name" => $fetchedBranch->name,
                    "short_name" => $fetchedBranch->short_name,
                    "date_assigned" => $branch->date_assigned,
                    "reason_for_deletion" => $request->reason
                ]
            ])
            ->log('Assigned branch deleted');

            $branch->delete();
            $employee->update([
                "branch_id" => $employee->latest_branch->branch_id
            ]);
            DB::commit();
            return $this->sendResponse(["message" => "Branch deleted successfully"]);
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

            $latestActingCapacity = ActingCapacity::where("employee_id", $employee->id)
            ->where("status", "Pending")
            ->latest();

            if ($latestActingCapacity) {
                $latestActingCapacity->update([
                    "branch_id" => $request->branch_id
                ]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Branch updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
