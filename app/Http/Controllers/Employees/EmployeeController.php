<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class EmployeeController extends Controller
{
    public function addEmployee(Request $request){
        try {
            $request->validate([
                "type" => "required",
                "first_name" => "required",
                "last_name" => "required",
                "gender" => "required",
                "position_id" => "required",
                "branch_id" => "required",
                "home_branch_id" => "required",
                "date_hired" => "required",
            ]);
            Employees::create($request->all());

            return $this->sendResponse(["message" => "Employee added successfully." ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllEmployees(Request $request) { 
        try {
            $perPage = $request->per_page ?? 10;

            $employees = Employees::with(["position", "agency", "branch", "station"])->paginate($perPage);
            return $this->sendResponse($employees);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
