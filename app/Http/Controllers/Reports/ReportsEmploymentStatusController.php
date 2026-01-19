<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\OtherCompany\OtherEmployee;
use App\Models\Station;
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
            ->where("status", "active")
            ->orderBy('created_at', 'desc')
            ->get();

            if ($employees->count() > 0) {
                $branchOIC = Employee::with(["current_position"])
                ->where("employee_number", $branch->branch_head_employee_number)->first();

                if (!$branchOIC) {
                    $branchOIC = OtherEmployee::with(["current_position"])
                    ->where("employee_number", $branch->branch_head_employee_number)->first();
                }

                $groupHead = Employee::with(["current_position"])
                ->where("employee_number", $branch->group_head_employee_number)->first();

                if (!$groupHead) {
                    $groupHead = OtherEmployee::with(["current_position"])
                    ->where("employee_number", $branch->group_head_employee_number)->first();
                }
                $groupedEmployees = $employees->groupBy("station_id");
                $data = [];

                foreach($groupedEmployees as $group) {
                    $branch_station = Station::find($group[0]->station_id);
                    $recommendedBy = $branchOIC;
                    $notedBy = $groupHead;
                    $notedBy1 = null;
                    $station = strtolower($branch_station->name);
                    if ($station === "back of the house") {
                        $recommendedBy = $groupHead;

                        $notedBy = Employee::with(["current_position"])
                        ->where("employee_number", "0007413")
                        ->first();
                    }
                    if ($station === "front of the house") {
                        $recommendedBy = $groupHead;

                        $notedBy1 = Employee::with(["current_position"])
                        ->where("employee_number", "639")
                        ->first();

                        if (!$notedBy1) {
                            $notedBy1 = OtherEmployee::with(["current_position"])
                            ->where("employee_number", "639")
                            ->first();
                        }

                        $notedBy = Employee::with(["current_position"])
                        ->where("employee_number", "M003")
                        ->first();

                        if (!$notedBy) {
                            $notedBy = OtherEmployee::with(["current_position"])
                            ->where("employee_number", "M003")
                            ->first();
                        }
                    }

                    $data[] = [
                        "branch_oic" => $branchOIC,
                        "group_head" => $groupHead,
                        "date" => now()->toDateString(),
                        "branch" => $branch,
                        "data" => $group,
                        "month" => Carbon::parse("01-".$request->month."-".$request->year)->format("F"),
                        "year" => $request->year,
                        "recommended_by" => $recommendedBy,
                        "noted_by" => $notedBy,
                        "noted_by_1" => $notedBy1,
                    ];
                }

                return $this->sendResponse([
                    "employees" => $employees,
                    "data" => $data
                ]);
            }

            return $this->sendError(["message" => "No employees found"]);

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
