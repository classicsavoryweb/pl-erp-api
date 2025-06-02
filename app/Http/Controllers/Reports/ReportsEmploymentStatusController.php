<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;

class ReportsEmploymentStatusController extends Controller
{
    public function getEmploymentStatusReports(Request $request) { 
        try {
            $status = EmployeeStatus::where("description", "Probationary")->first();
            $firstDay = Carbon::create($request->year, $request->month, 1)->toDateString();
            $lastDay = Carbon::create($request->year, $request->month, 1)->endOfMonth()->toDateString();

            $branch = Branch::find($request->branch_id);

            $employees = Employee::with(['current_position', 'latest_contract', 'contracts'])
            ->whereHas('latest_contract', function ($query) use ($firstDay, $lastDay) {
                $query->whereBetween('contract_end_date', [$firstDay, $lastDay]);
            })
            ->where('employee_status_id', $status->id)
            ->where('branch_id', $request->branch_id)
            ->orderBy('created_at', 'desc')
            ->where("status", "active")
            ->get();

            $branchOIC = Employee::with(["current_position"])
            ->where("employee_number", $branch->branch_head_employee_number)->first();
            
            return $this->sendResponse([
                "branch_oic" => $branchOIC,
                "date" => now()->toDateString(),
                "branch" => $branch,
                "data" => $employees,
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
