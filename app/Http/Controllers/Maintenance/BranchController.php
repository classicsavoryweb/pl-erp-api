<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\BranchExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class BranchController extends Controller
{
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

            $branches = Branch::where("status", "active")->get();
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
                "travel_time" => "required",
                "device_name" => "required",
                "short_name" => "required|unique:branches,short_name",
                "address" => "required",
                "location" => "required",
                "company" => "required",
                "date_open" => "required",
                "branch_email" => "required",
                "rate_code" => "required",
                'branch_head_employee_number' => "required",
                "group_head_employee_number" => "required",
                "mancom_employee_number" => "required",
            ]);

            $count = Branch::count();
            $format = $request->company === "Tony Ting Food Concepts Inc." ? "TTFC" : "SFI";
            $branchNumber = $format . str_pad(++$count, 5, '0', STR_PAD_LEFT);
            $bhEmployee = Employee::where("employee_number", $request->branch_head_employee_number)->first();
            $ghEmployee = Employee::where("employee_number", $request->group_head_employee_number)->first();
            $mancom = Employee::where("employee_number", $request->mancom_employee_number)->first();

            $kitchenOIC = "";
            if ($request->has('kitchen_oic_employee_number')) {
                $kitchenOIC = Employee::where("employee_number", $request->kitchen_oic_employee_number)->first();
            }

            Branch::create([
                "name" => $request->name,
                "branch_number" => $branchNumber,
                "status" => $request->status,
                "travel_time" => $request->travel_time,
                "device_name" => $request->device_name,
                "short_name" => $request->short_name,
                "address" => $request->address,
                "location" => $request->location,
                "date_open" => $request->date_open,
                "company" => $request->company,
                "rate_code" => $request->rate_code,
                "branch_email" => $request->branch_email,
                'branch_head_employee_number' => $bhEmployee->employee_number,
                "group_head_employee_number" => $ghEmployee->employee_number,
                'branch_head' => $bhEmployee->full_name,
                "group_head" => $ghEmployee->full_name,
                "mancom_employee_number" => $mancom->employee_number,
                "mancom" => $mancom->full_name,
                "kitchen_oic_employee_number" => $kitchenOIC->employee_number ?? null,
                "kitchen_oic" => $kitchenOIC->full_name ?? null, 
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
                "travel_time" => "required",
                "device_name" => "required",
                "branch_number" => "required|unique:branches,branch_number,".$id,
                "short_name" => "required|unique:branches,short_name,".$id,
                "address" => "required",
                "location" => "required",
                "company" => "required",
                "date_open" => "required",
                "branch_email" => "required",
                "rate_code" => "required",
                'branch_head_employee_number' => "required",
                "group_head_employee_number" => "required",
                "mancom_employee_number" => "required",
            ]);

            $bhEmployee = Employee::where("employee_number", $request->branch_head_employee_number)->first();
            $ghEmployee = Employee::where("employee_number", $request->group_head_employee_number)->first();
            $mancom = Employee::where("employee_number", $request->mancom_employee_number)->first();

            $kitchenOIC = "";
            if ($request->has('kitchen_oic_employee_number')) {
                $kitchenOIC = Employee::where("employee_number", $request->kitchen_oic_employee_number)->first();
            }
            Branch::where("id", $id)->update([
                "name" => $request->name,
                "status" => $request->status,
                "travel_time" => $request->travel_time,
                "device_name" => $request->device_name,
                "short_name" => $request->short_name,
                "branch_number" => $request->branch_number,
                "address" => $request->address,
                "location" => $request->location,
                "date_open" => $request->date_open,
                "company" => $request->company,
                "rate_code" => $request->rate_code,
                "branch_email" => $request->branch_email,
                'branch_head_employee_number' => $bhEmployee->employee_number,
                "group_head_employee_number" => $ghEmployee->employee_number,
                'branch_head' => $bhEmployee->full_name,
                "group_head" => $ghEmployee->full_name,
                "mancom_employee_number" => $mancom->employee_number,
                "mancom" => $mancom->full_name,
                "kitchen_oic_employee_number" => $kitchenOIC->employee_number ?? null,
                "kitchen_oic" => $kitchenOIC->full_name ?? null, 
            ]);

            return $this->sendResponse(["message" => "Branch updated successfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
