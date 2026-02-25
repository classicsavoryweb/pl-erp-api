<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Exports\Employees\EmployeePromotionsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Models\ActingCapacity;
use App\Models\Employee;
use Exception;
use DB;

class EmployeePromotionController extends Controller
{
    public function getPromotionSummary(Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ?? 10;
            $employee = Employee::where("employee_number", $employee_number)->firstOrFail();

            $positions = ActingCapacity::with(["current_position","recommended_position"])
                ->where("employee_id", $employee->id)
                ->orderBy("start_date", "DESC");
            return $this->sendResponse($positions->paginate($perPage));
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}


