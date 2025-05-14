<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\BankDetail;
use App\Models\BranchEmployee;
use App\Models\Contract;
use App\Models\EducationHistory;
use App\Models\Employee;
use App\Models\EmployeeOffense;
use App\Models\EmployeePosition;
use App\Models\EmployeeStatus;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use Spatie\Activitylog\Models\Activity;

class EmployeeController extends Controller
{
    public function addEmployee(Request $request){
        try {
            DB::beginTransaction();
            $request->validate([
                "type" => "required",
                "first_name" => "required",
                "last_name" => "required",
                "gender" => "required",
                "position_id" => "required",
                "branch_id" => "required",
                "home_branch_id" => "required",
                "date_hired" => "required",
                "birthday" => "required",
                "contact_number" => "required",
                "contract_duration" => "required",
            ]);

            $employeeCount = Employee::count();
            $employeeNumber = str_pad(++$employeeCount, 7, '0', STR_PAD_LEFT);
            $pendingStatus = EmployeeStatus::where('description', "Pending")->first();
            $fields = $request->except(["contract_duration"]);
            $position = Position::find($request->position_id);
            $biometricsNumber = "";

            if ($request->type === "Direct") {
                $biometricsNumber = "10".$employeeNumber;
            }
            if ($request->type === "Agency") {
                $agency = Agency::find($request->agency_id);

                if ($agency->name === "MSC") {
                    $biometricsNumber = "50".$employeeNumber;
                } else if ($agency->name === "GMI") {
                    $biometricsNumber = "70".$employeeNumber;
                } else if ($agency->name === "CNT") {
                    $biometricsNumber = "11".$employeeNumber;
                } else {
                    $biometricsNumber = "12".$employeeNumber;
                }
            }

            $employee = Employee::create([
                "employee_number" => $employeeNumber,
                "employee_status_id" => $pendingStatus->id,
                "station_id" => $position->station_id,
                "biometric_number" => $biometricsNumber,
                ...$fields
            ]);

            $startDate = Carbon::parse($request->date_hired);
            $contractEndDate = $startDate->addMonths($request->contract_duration)->toDateString();

            BranchEmployee::create([
                "employee_id" => $employee->id,
                "branch_id" => $request->branch_id,
                "date_assigned" => $request->date_hired
            ]);
            EmployeePosition::create([
                "employee_id" => $employee->id,
                "position_id" => $request->position_id,
                "date_started" => $request->date_hired
            ]);
            Contract::create([
                "employee_id" => $employee->id,
                "employee_number" => $employee->employee_number,
                "contract_start_date" => $employee->date_hired,
                "contract_end_date" => $contractEndDate,
                "contract_number" => "1st",
                "remarks" => "1st contract",
                "status" => "active"
            ]);
            
            DB::commit();
            return $this->sendResponse(["message" => "Employee added successfully." ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    
    public function getEmployee(Request $request, $employee_number) {
        try {
            $employee = Employee::with([
                    "current_position",
                    "agency",
                    "current_branch",
                    "station",
                    "employee_status",
                    "home_branch",
                    "branches",
                    "positions",
                    "education",
                    "dependents",
                    "contracts"
                ])->where("employee_number", $employee_number)
                ->first();

            return $this->sendResponse(["employee" => $employee]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    public function getAllEmployees(Request $request) { 
        try {
            $perPage = $request->per_page ?? 10;

            $employees = Employee::with(["current_position", "agency", "current_branch", "station"])
            ->orderBy("date_hired","desc")
            ->paginate($perPage);
            return $this->sendResponse($employees);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getEmployeeAuditLogs(Request $request, $employeeNumber) {
        try {
            $perPage = $request->per_page ??10;

            $employee = Employee::where("employee_number", $employeeNumber)->first();
            $activities = Activity::with("causer")
                ->forSubject($employee)
                ->orderBy("created_at", "DESC")
                ->paginate($perPage);

            return $this->sendResponse($activities);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getEmployees(Request $request) {
        try {

            $employees = Employee::where("status", "active")->get();
            return $this->sendResponse(["employees" => $employees]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

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

    public function updateEmployeeDetails(Request $request, $employee_number) { 
        try {
            DB::beginTransaction();
            $employee = Employee::where("employee_number", $employee_number)->first();

            $educationData = collect($request->education)
            ->except(["created_at", "updated_at"])
            ->toArray();

            if ($employee->education) {
                $employee->education()->update($educationData);
                $education = $employee->education;
            } else {
                $educationData['employee_id'] = $employee->id;
                $educationData['employee_number'] = $employee->employee_number;
                $education = EducationHistory::create($educationData);
            }

            $employee->update([
                // "image" => $request->image,
                "first_name" => $request->first_name,
                "middle_name" => $request->middle_name,
                "last_name" => $request->last_name,
                "email" => $request->email,
                "civil_status" => $request->civil_status,
                "gender" => $request->gender,
                "height" => $request->height,
                "birthday" => $request->birthday,
                "birthplace" => $request->birthplace,
                "address" => $request->address,
                "provincial_address" => $request->provincial_address,
                "region" => $request->region,
                "contact_number" => $request->contact_number,
                "tax_type" => $request->tax_type,
                "tin" => $request->tin,
                "sss" => $request->sss,
                "philhealth" => $request->philhealth,
                "pagibig" => $request->pagibig,
                "referred_by" => $request->referred_by,
                "date_hired" => $request->date_hired,
                "remarks" => $request->remarks,
                "regularization_date" => $request->regularization_date,
                "separation_date" => $request->separation_date,
                "separation_reason" => $request->separation_reason,
                "emergency_contact_name" => $request->emergency_contact_name,
                "emergency_contact_number" => $request->emergency_contact_number,
                "emergency_contact_address" => $request->emergency_contact_address,
                "emergency_contact_relationship" => $request->emergency_contact_relationship,
                "education_history_id" => $education->id,
                "employee_status_id" => $request->employee_status_id,
                "status" => $request->status
            ]);

            if (!empty($request->dependents) && count($request->dependents) > 0) {
                foreach($request->dependents as $dependant) {
                    $employee->dependents()->updateOrCreate([
                        "employee_number" => $employee->employee_number,
                        ...$dependant
                    ]);
                }
            }

            DB::commit();
            return $this->sendResponse(["message" => "Employee updated successfully"]);
        } catch (Exception $e) {

            DB::rollBack();
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
                "bank_number" => "required",
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
                "bank_number" => "required",
                "remarks" => "required"
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->bank_details()->where("id", $request->id)->update([
                ...$request->except(["id"])
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Bank updated successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getEmployeeOffenses (Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $employee = Employee::where("employee_number", $employee_number)->first();
            $offenses = EmployeeOffense::where("employee_id", $employee->id)
                ->with(["offense", "sanction"])
                ->orderBy("date_committed","DESC")
                ->paginate($perPage);

            return $this->sendResponse($offenses);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function addOffense (Request $request, $employee_number) {
        try {
            DB::beginTransaction();
            $request->validate([        
                'offense_id' => "required",
                'sanction_id' => "required",
                'transgression' => "required",
                'repetition' => "required",
                'summary' => "required",
                'date_committed' => "required",
                'offense_action' => "required",
                'date_served_ao' => "required",
                'suspension_dates' => "required",
                'date_served_da' => "required",
                'remarks' => "required",
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->offenses()->create([
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Offense added successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editOffense (Request $request, $employee_number) {
        try {
            DB::beginTransaction();
            $request->validate([   
                "id" => "required",     
                'offense_id' => "required",
                'sanction_id' => "required",
                'transgression' => "required",
                'repetition' => "required",
                'summary' => "required",
                'date_committed' => "required",
                'offense_action' => "required",
                'date_served_ao' => "required",
                'suspension_dates' => "required",
                'date_served_da' => "required",
                'remarks' => "required",
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->offenses()->where("id", $request->id)->update([
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Offense updated successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
