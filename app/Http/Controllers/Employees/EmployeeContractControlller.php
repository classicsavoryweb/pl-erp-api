<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Rate;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Models\Employee;
use Exception;
use DB;
use Carbon\Carbon;

class EmployeeContractControlller extends Controller
{
    public function getEmployeeContractHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $contracts = Contract::where("employee_id", $employee->id)
                ->orderBy("contract_start_date","DESC")
                ->paginate($perPage);

            foreach($contracts as $contract) {
                $date1 = Carbon::parse($contract->contract_start_date);
                $date2 = Carbon::parse($contract->contract_end_date);

                $contract->duration = $date1->diffInMonths($date2);
            }

            return $this->sendResponse($contracts);
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

    public function getContractDetails ($id) {
        try {
            $contract = Contract::find($id);
            $employee = $contract->employee()->first();
            $position = $employee->current_position()->first();
            $branch = $employee->current_branch()->first();

            $date1 = Carbon::parse($contract->contract_start_date);
            $date2 = Carbon::parse($contract->contract_end_date);
            $duration = $date1->diffInMonths($date2);

            $salary_amount = 0.00;
            $salary = $position->salary()->first();

            if ($salary->rate_category !== "minimum") {
                $salary_amount = ($salary->basic_salary / 26) / 8;
            } else {
                $rate = Rate::where("code", $branch->rate_code)->first();
                $salary_amount = $rate->minimum / 8;
            }

            $previousContract = $employee->contracts()
            ->where('contract_start_date', '<', $contract->contract_start_date)
            ->orderBy('contract_start_date', 'desc')
            ->first();

            $middleInitial = strtoupper(substr($employee->middle_name, 0, 1));
            $fullName = strtolower($employee->first_name . ' ' . $middleInitial . '. ' . $employee->last_name);
            $properFullName = ucwords($fullName);

            return $this->sendResponse([
                "first_name" => $employee->first_name,
                "middle_name" => $employee->middle_name,
                "last_name" => $employee->last_name,
                "gender" => $employee->gender,
                "address" => $employee->address,
                "date_hired" => $employee->date_hired,
                "contract_start_date" => $contract->contract_start_date,
                "contract_end_date" => $contract->contract_end_date,
                "previous_contract_start_date" => $previousContract->contract_start_date ?? null,
                "previous_contract_end_date" => $previousContract->contract_end_date ?? null,
                "duration" => $duration,
                "proper_full_name" => $properFullName,
                "position" => $position->description,
                "branch" => $branch->name,
                "salary_amount" => $salary_amount
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
