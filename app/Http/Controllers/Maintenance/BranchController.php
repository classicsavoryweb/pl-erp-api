<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\Request;
use Exception;

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
 
            $branches = $branches->paginate($perPage);

            return $this->sendResponse($branches);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
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
                "short_name" => "required|unique:branches,short_name",
                "address" => "required",
                "location" => "required",
                "company" => "required",
                "date_open" => "required",
                "branch_email" => "required",
                "rate_code" => "required",
                'branch_head_employee_number',
                "group_head_employee_number",
            ]);

            $count = Branch::count();
            $format = $request->company === "Tony Ting Food Concepts Inc." ? "TTFC" : "SFI";
            $branchNumber = $format . str_pad(++$count, 5, '0', STR_PAD_LEFT);
            $bhEmployee = Employee::where("employee_number", $request->branch_head_employee_number)->first();
            $ghEmployee = Employee::where("employee_number", $request->group_head_employee_number)->first();

            Branch::create([
                "name" => $request->name,
                "branch_number" => $branchNumber,
                "status" => $request->status,
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
            ]);

            return $this->sendResponse(["message" => "Branch created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editBranch(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required|unique:branches,name,except,",$id,
                "status" => "required",
                "short_name" => "required|unique:branches,short_name,except,",$id,
                "address" => "required",
                "location" => "required",
                "company" => "required",
                "date_open" => "required",
                "branch_email" => "required",
                "rate_code" => "required",
                'branch_head_employee_number',
                "group_head_employee_number",
            ]);

            $bhEmployee = Employee::where("employee_number", $request->branch_head_employee_number)->first();
            $ghEmployee = Employee::where("employee_number", $request->group_head_employee_number)->first();

            Branch::where("id", $id)->update([
                "name" => $request->name,
                "status" => $request->status,
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
            ]);

            return $this->sendResponse(["message" => "Branch created successfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
