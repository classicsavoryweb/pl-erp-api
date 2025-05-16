<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeOffense;
use Exception;
use DB;

class EmployeeOffensesControlller extends Controller
{
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
