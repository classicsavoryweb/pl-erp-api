<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeeContractsExport;
use App\Http\Controllers\Controller;
use App\Models\EmployeeStatus;
use App\Models\Rate;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Models\Employee;
use Exception;
use DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeContractControlller extends Controller
{
    public function getEmployeeContractHistory(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $contracts = Contract::where("employee_id", $employee->id)
                ->orderBy("contract_start_date","DESC");

            if($request->has(["export"]) && $request->export) {
                $contracts = $contracts->get();
                return Excel::download(new EmployeeContractsExport($contracts), "contracts.xls");
            }

            $contracts = $contracts->paginate($perPage);
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
    
    public function voidEmployeeContract($id) {
        try {
        $contract = Contract::findOrFail($id);
        $contract->status = 'voided';
        $contract->save();

        return $this->sendResponse([
            "message" => "Contract voided successfully",
            "contract_id" => $contract->id
        ]);
    } catch (\Exception $e) {
        return $this->sendError(["message" => $e->getMessage()], 500);
        }
    }

    public function exportEmployeeContractHistory(Request $request, $employee_number) {
        $request["export"] = true;

        return $this->getEmployeeContractHistory($request, $employee_number);
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
            $count = $employee->contracts()->where('status', '!=', 'voided')->count();
            $lastContract = $employee->contracts()->latest('id')->first();

             if ($lastContract && $lastContract->status === 'voided') {
                $request->merge([
                'contract_start_date' => $lastContract->contract_end_date
                ]);
            }

            if ($request->status === 'active') {
                $employee->contracts()->where('status', 'active')->update(['status' => 'inactive']);
            }

            $employee->contracts()->create([
                "employee_number" => $employee->employee_number,
                "contract_number" => $this->ordinal($count + 1),
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
            $employee = Employee::where("employee_number", $employee_number)->firstOrFail();
            $contract = Contract::find($request->id);
            $contract->update([
                "contract_start_date" => $request->contract_start_date,
                "contract_end_date" => $request->contract_end_date,
                "status" => $request->status,
                "remarks" => $request->remarks ?? null,
            ]);

            if ($request->status === "inactive") {
                if ($request->has("employee_status")) {
                    $employee->status = $request->employee_status;
                }
                if ($request->has("employment_status")) {
                    $employee->employee_status_id = $request->employment_status;
                }
                if ($request->has("regularization_date")) {
                    $employee->regularization_date = $request->regularization_date;
                }
                if ($request->has("separation_reason")) {
                    $separationReason = EmployeeStatus::find($request->separation_reason);
                    $employee->separation_reason = $separationReason->description;
                }
                if ($request->has("separation_date")) {
                    $employee->separation_date = $request->separation_date;
                }
                if ($employee->isDirty()) {
                    $employee->save();
                }
            }

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
            $branch = $employee->home_branch()->first();

            $date1 = Carbon::parse($contract->contract_start_date);
            $date2 = Carbon::parse($contract->contract_end_date);
            $duration = $date1->diffInMonths($date2);
            $durationType = "months";

            if ($duration == 0) {

                $days = $date1->diffInDays($date2);
                $weeks = intdiv($days, 7);
                $remainderDays = $days % 7;

                if ($remainderDays == 5 | 6) {
                    $weeks += 1;
                }

                $duration = $weeks;
                $durationType = "weeks";
            }

            $salary_amount = 0.00;
            $salary = $position->salary()->first();

            if ($salary) {
                if ($salary->rate_category !== "minimum") {
                    $salary_amount = ($salary->basic_salary / 26) / 8;
                } else {
                    $rate = Rate::where("code", $branch->rate_code)->first();
                    $salary_amount = $rate->minimum / 8;
                }
            } else {
                return $this->sendError(["message" => "Salary position not existing" ]);
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
                "duration_type" => $durationType,
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
