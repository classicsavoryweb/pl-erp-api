<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\BranchExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\OtherCompany\OtherEmployee;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class BranchController extends Controller
{
    private function syncEmployeeOicStatuses(array $employeeNumbers): void
    {
        $employeeNumbers = array_values(array_unique(array_filter($employeeNumbers)));

        if (empty($employeeNumbers)) {
            return;
        }

        foreach ($employeeNumbers as $employeeNumber) {
            $employee = Employee::where("employee_number", $employeeNumber)->first();

            if (!$employee) {
                continue;
            }

            $hasBranchOicAssignment = Branch::where(function ($query) use ($employeeNumber) {
                $query->where("branch_head_employee_number", $employeeNumber)
                    ->orWhere("kitchen_oic_employee_number", $employeeNumber);
            })->exists();

            $hasPositionOicAssignment = ($employee->latest_position?->oic_assignment ?? null) === "Yes";
            $newOicStatus = ($hasBranchOicAssignment || $hasPositionOicAssignment) ? "OIC" : null;

            if ($employee->oic_status !== $newOicStatus) {
                $employee->update([
                    "oic_status" => $newOicStatus,
                ]);
            }
        }
    }

    private function resolveEmployeeByNumber(?string $employeeNumber): ?array
    {
        if (!$employeeNumber) {
            return null;
        }

        $employee = Employee::where("employee_number", $employeeNumber)->first();
        if ($employee) {
            return [
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
            ];
        }

        $otherEmployee = OtherEmployee::where("employee_number", $employeeNumber)
            ->orWhere("employee_number_2", $employeeNumber)
            ->first();

        if ($otherEmployee) {
            return [
                'employee_number' => $otherEmployee->employee_number,
                'full_name' => $otherEmployee->full_name,
            ];
        }

        return null;
    }
    public function getAllBranches(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $branches = Branch::query();

            if ($request->has(["column_type", "key_word"])) {
               $branches = $branches->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["date_from", "date_to"])) {
                $branches = $branches->whereBetween('date_open', [$request->date_from, $request->date_to]);
            }

            if ($request->has(["status"])) {
                $branches = $branches->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $branches = $branches->get();
                return Excel::download(new BranchExport($branches), "branches.xls");
            }
 
            $branches = $branches->paginate($perPage);

            return $this->sendResponse($branches);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportBranches(Request $request) {
        $request["export"] = true;

        return $this->getAllBranches($request);
    }

    public function getBranches(Request $request) {
        try {
            $branches = Branch::where("status", "active")
            ->orderBy("name", "ASC")
            ->get();

            return $this->sendResponse(["branches" => $branches]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getFilterBranches(Request $request) {
        try {
            $branches = Branch::orderBy("name", "ASC")
            ->get();

            return $this->sendResponse(["branches" => $branches]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createBranch(Request $request) {
        try {
            $request->validate([
                "name" => "required|unique:branches,name",
                "status" => "required",
                //"travel_time" => "required",
                //"device_name" => "required",
                "short_name" => "required|unique:branches,short_name",
                "type" => "required",
                "address" => "required",
                "location" => "required",
                "company" => "required",
                "date_open" => "required",
                "branch_email" => "required",
                "rate_code" => "required",
                'branch_head_employee_number' => "nullable|string",
                "group_head_employee_number" => "nullable|string",
                "kitchen_oic_employee_number" => "nullable|string",
                "mancom_employee_number" => "nullable|string",
                // "expense_debit" => "required",
                // "expense_credit" => "required",
                // "advance_debit" => "required",
                // "advance_credit" => "required",
                // "ho_13th_debit" => "required",
                // "ho_13th_debit_1" => "required",
                // "ho_13th_credit" => "required",
                // "branch_ho_13th_debit" => "required",
                // "branch_ho_13th_debit_1" => "required",
                // "branch_ho_13th_credit" => "required",
                // "branch_ho_stat_debit" => "required",
                // "branch_ho_stat_credit" => "required",
            ]);

            $count = Branch::count();
            $format = $request->company === "Tony Ting Food Concepts Inc." ? "TTFC" : "SFI";
            $branchNumber = $format . str_pad(++$count, 5, '0', STR_PAD_LEFT);
            $bhEmployee = $this->resolveEmployeeByNumber($request->branch_head_employee_number);
            $ghEmployee = $this->resolveEmployeeByNumber($request->group_head_employee_number);
            $mancom = $this->resolveEmployeeByNumber($request->mancom_employee_number);
            $kitchenOIC = $this->resolveEmployeeByNumber($request->kitchen_oic_employee_number);

            if ($request->filled('branch_head_employee_number') && !$bhEmployee) {
                return $this->sendError(["message" => "Branch head employee number not found."]);
            }
            if ($request->filled('group_head_employee_number') && !$ghEmployee) {
                return $this->sendError(["message" => "Group head employee number not found."]);
            }
            if ($request->filled('mancom_employee_number') && !$mancom) {
                return $this->sendError(["message" => "Mancom employee number not found."]);
            }
            if ($request->filled('kitchen_oic_employee_number') && !$kitchenOIC) {
                return $this->sendError(["message" => "Kitchen OIC employee number not found."]);
            }

            $branch = Branch::create([
                "name" => $request->name,
                "branch_number" => $branchNumber,
                "status" => $request->status,
                "travel_time" => $request->travel_time,
                "device_name" => $request->device_name ?? null,
                "short_name" => $request->short_name,
                "type" => $request->type,
                "address" => $request->address,
                "location" => $request->location,
                "date_open" => $request->date_open,
                "company" => $request->company,
                "rate_code" => $request->rate_code,
                "branch_email" => $request->branch_email,
                'branch_head_employee_number' => $bhEmployee['employee_number'] ?? null,
                "group_head_employee_number" => $ghEmployee['employee_number'] ?? null,
                'branch_head' => $bhEmployee['full_name'] ?? null,
                "group_head" => $ghEmployee['full_name'] ?? null,
                "mancom_employee_number" => $mancom['employee_number'] ?? null,
                "mancom" => $mancom['full_name'] ?? null,
                "kitchen_oic_employee_number" => $kitchenOIC['employee_number'] ?? null,
                "kitchen_oic" => $kitchenOIC['full_name'] ?? null, 
                "expense_debit" => $request->expense_debit ?? null, 
                "expense_credit" => $request->expense_credit ?? null, 
                "advance_debit" => $request->advance_debit ?? null, 
                "advance_credit" => $request->advance_credit ?? null, 
                "ho_13th_debit" => $request->ho_13th_debit ?? null,
                "ho_13th_debit_1" => $request->ho_13th_debit_1 ?? null,
                "ho_13th_credit" => $request->ho_13th_credit ?? null,
                "branch_ho_13th_debit" => $request->branch_ho_13th_debit ?? null,
                "branch_ho_13th_debit_1" => $request->branch_ho_13th_debit_1 ?? null,
                "branch_ho_13th_credit" => $request->branch_ho_13th_credit ?? null,
                "branch_ho_stat_debit" => $request->branch_ho_stat_debit ?? null,
                "branch_ho_stat_credit" => $request->branch_ho_stat_credit ?? null,
            ]);

            $this->syncEmployeeOicStatuses([
                $branch->branch_head_employee_number,
                $branch->kitchen_oic_employee_number,
            ]);

            return $this->sendResponse(["message" => "Branch created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editBranch(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required|unique:branches,name,".$id,
                "status" => "required",
                //"travel_time" => "required",
                //"device_name" => "required",
                "branch_number" => "required|unique:branches,branch_number,".$id,
                "short_name" => "required|unique:branches,short_name,".$id,
                "type" => "required",
                "address" => "required",
                "location" => "required",
                "company" => "required",
                "date_open" => "required",
                "branch_email" => "required",
                "rate_code" => "required",
                'branch_head_employee_number' => "nullable|string",
                "group_head_employee_number" => "nullable|string",
                "kitchen_oic_employee_number" => "nullable|string",
                "mancom_employee_number" => "nullable|string",
                // "expense_debit" => "required",
                // "expense_credit" => "required",
                // "advance_debit" => "required",
                // "advance_credit" => "required",
                // "ho_13th_debit" => "required",
                // "ho_13th_debit_1" => "required",
                // "ho_13th_credit" => "required",
                // "branch_ho_13th_debit" => "required",
                // "branch_ho_13th_debit_1" => "required",
                // "branch_ho_13th_credit" => "required",
                // "branch_ho_stat_debit" => "required",
                // "branch_ho_stat_credit" => "required",
            ]);

            $bhEmployee = $this->resolveEmployeeByNumber($request->branch_head_employee_number);
            $ghEmployee = $this->resolveEmployeeByNumber($request->group_head_employee_number);
            $mancom = $this->resolveEmployeeByNumber($request->mancom_employee_number);
            $kitchenOIC = $this->resolveEmployeeByNumber($request->kitchen_oic_employee_number);

            if ($request->filled('branch_head_employee_number') && !$bhEmployee) {
                return $this->sendError(["message" => "Branch head employee number not found."]);
            }
            if ($request->filled('group_head_employee_number') && !$ghEmployee) {
                return $this->sendError(["message" => "Group head employee number not found."]);
            }
            if ($request->filled('mancom_employee_number') && !$mancom) {
                return $this->sendError(["message" => "Mancom employee number not found."]);
            }
            if ($request->filled('kitchen_oic_employee_number') && !$kitchenOIC) {
                return $this->sendError(["message" => "Kitchen OIC employee number not found."]);
            }
            $branch = Branch::findOrFail($id);
            $affectedEmployeeNumbers = [
                $branch->branch_head_employee_number,
                $branch->kitchen_oic_employee_number,
                $bhEmployee['employee_number'] ?? null,
                $kitchenOIC['employee_number'] ?? null,
            ];

            $branch->update([
                "name" => $request->name,
                "status" => $request->status,
                "travel_time" => $request->travel_time,
                "device_name" => $request->device_name ?? null,
                "short_name" => $request->short_name,
                "branch_number" => $request->branch_number,
                "type" => $request->type,
                "address" => $request->address,
                "location" => $request->location,
                "date_open" => $request->date_open,
                "company" => $request->company,
                "rate_code" => $request->rate_code,
                "branch_email" => $request->branch_email,
                'branch_head_employee_number' => $bhEmployee['employee_number'] ?? null,
                "group_head_employee_number" => $ghEmployee['employee_number'] ?? null,
                'branch_head' => $bhEmployee['full_name'] ?? null,
                "group_head" => $ghEmployee['full_name'] ?? null,
                "mancom_employee_number" => $mancom['employee_number'] ?? null,
                "mancom" => $mancom['full_name'] ?? null,
                "kitchen_oic_employee_number" => $kitchenOIC['employee_number'] ?? null,
                "kitchen_oic" => $kitchenOIC['full_name'] ?? null,
                "expense_debit" => $request->expense_debit ?? null, 
                "expense_credit" => $request->expense_credit ?? null, 
                "advance_debit" => $request->advance_debit ?? null, 
                "advance_credit" => $request->advance_credit ?? null,
                "ho_13th_debit" => $request->ho_13th_debit ?? null,
                "ho_13th_debit_1" => $request->ho_13th_debit_1 ?? null,
                "ho_13th_credit" => $request->ho_13th_credit ?? null,
                "branch_ho_13th_debit" => $request->branch_ho_13th_debit ?? null,
                "branch_ho_13th_debit_1" => $request->branch_ho_13th_debit_1 ?? null,
                "branch_ho_13th_credit" => $request->branch_ho_13th_credit ?? null,
                "branch_ho_stat_debit" => $request->branch_ho_stat_debit ?? null,
                "branch_ho_stat_credit" => $request->branch_ho_stat_credit ?? null,
            ]);

            $this->syncEmployeeOicStatuses($affectedEmployeeNumbers);

            return $this->sendResponse(["message" => "Branch updated successfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
