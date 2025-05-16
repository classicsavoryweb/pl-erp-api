<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Models\Employee;
use Exception;
use DB;
class EmployeeContractControlller extends Controller
{
    public function getEmployeeContractHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $branches = Contract::where("employee_id", $employee->id)
                ->orderBy("contract_start_date","DESC")
                ->paginate($perPage);

            return $this->sendResponse($branches);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function addContract(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "contract_start_date" => "required",
                "contract_end_date" => "required",
                "status" => "required",
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();
            $count = $employee->contracts()->count();
            $employee->contracts()->update(['status' => 'inactive']);
            $employee->contracts()->create([
                "employee_number" => $employee->employee_number,
                "contract_number" => $this->ordinal(++$count),
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Contract added successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editContract(Request $request, $employee_number) {
        try {
            DB::beginTransaction();

            $request->validate([
                "id" => "required",
                "contract_start_date" => "required",
                "contract_end_date" => "required",
                "status" => "required",
            ]);
            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->contracts()->where("id", $request->id)->update([
                ...$request->all()
            ]);
            
            DB::commit();
            return $this->sendResponse(["message" => "Contract updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
