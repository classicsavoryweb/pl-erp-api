<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\Request;
use Exception;

class DashboardController extends Controller
{
    public function getSummaryDetails(Request $request) {
        try {
            $branches = Branch::where("status", "active")->count();
            $probationaryEmployees = Employee::where("status", "active")
            ->whereHas("employee_status", function($query) {
                $query->where("description", "Probationary");
            })
            ->count();
            $regularEmployees = Employee::where("status", "active")
            ->whereHas("employee_status", function($query) {
                $query->where("description", "Regular");
            })
            ->count();
            $contractualEmployees = Employee::where("status", "active")
            ->whereHas("employee_status", function($query) {
                $query->where("description", "Contractual");
            })
            ->count();

            return $this->sendResponse([
                "branches" => $branches,
                "probationary_employees" => $probationaryEmployees,
                "regular_employees" => $regularEmployees,
                "contractual_employees" => $contractualEmployees
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
