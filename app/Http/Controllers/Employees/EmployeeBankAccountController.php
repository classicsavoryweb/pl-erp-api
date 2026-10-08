<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankDetail;
use App\Models\Employee;
use Exception;
use DB;

class EmployeeBankAccountController extends Controller
{
    public function getEmployeeBankDetailsHistory (Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $banks = BankDetail::where("employee_id", $employee->id)
                ->with(["bank_account_type"])
                ->orderBy("updated_at","DESC")
                ->paginate($perPage);

            return $this->sendResponse($banks);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function addBankDetails (Request $request, $employee_number) {
        try {
            DB::beginTransaction();
            $request->validate([
                "bank_account_type_id" => "required",
                "bank_number" => "required|unique:bank_details,bank_number",
                "remarks" => "required"
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->bank_details()->create([
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Bank added successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editBankDetails (Request $request, $employee_number) {
        try {
            DB::beginTransaction();
            $request->validate([
                "id" => "required",
                "bank_account_type_id" => "required",
                "bank_number" => "required|unique:bank_details,bank_number,".$request->id,
                "remarks" => "required"
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            $bank_details = BankDetail::find($request->id);
            $bank_details->update([
                "bank_account_type_id" => $request->bank_account_type_id,
                "bank_number" => $request->bank_number,
                "remarks" => $request->remarks,
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Bank updated successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
