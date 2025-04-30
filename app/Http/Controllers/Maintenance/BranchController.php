<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Exception;

class BranchController extends Controller
{
    public function getAllBranches(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $branches = Branch::paginate($perPage);
            return $this->sendResponse($branches);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function getBranches(Request $request) {
        try {

            $branches = Branch::where("status", "active")->get();
            return $this->sendResponse(["branches" => $branches]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function createBranch(Request $request) {
        try {
            $request->validate([
                "name" => "required",
                "branch_number" => "required|unique:branches",
                "status" => "required",
                "mall_admin" => "required",
                "short_name" => "required",
                "address" => "required",
                "location" => "required",
                "date_open" => "required",
            ]);
            Branch::create([
                "name" => $request->name,
                "branch_number" => $request->branch_number,
                "status" => $request->status,
                "mall_admin" => $request->mall_admin,
                "short_name" => $request->short_name,
                "address" => $request->address,
                "location" => $request->location,
                "date_open" => $request->date_open,
            ]);

            return $this->sendResponse(["message" => "Branch created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editBranch(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required",
                "branch_number" => "required|unique:branches,branch_number," . $id,
                "status" => "required",
                "mall_admin" => "required",
                "short_name" => "required",
                "address" => "required",
                "location" => "required",
                "date_open" => "required",
            ]);
            Branch::where("id", $id)->update([
                "name" => $request->name,
                "branch_number" => $request->branch_number,
                "status" => $request->status,
                "mall_admin" => $request->mall_admin,
                "short_name" => $request->short_name,
                "address" => $request->address,
                "location" => $request->location,
                "date_open" => $request->date_open,
            ]);

            return $this->sendResponse(["message" => "Branch created successfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
