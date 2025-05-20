<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\EmployeeStatus;
use Illuminate\Http\Request;
use Exception;

class EmployeeStatusController extends Controller
{
    public function getAllEmployeStatus(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $status = EmployeeStatus::query();
            if ($request->has(["column_type", "key_word"])) {
               $status = $status->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $status = $status->where("status", $request->status);
            }
            $status = $status->paginate($perPage);
            return $this->sendResponse($status);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getEmployeeStatus(Request $request) {
        try {
            $type = $request->type;

            $status = EmployeeStatus::where("status", "active")
            ->where('type', $type)
            ->get();
            return $this->sendResponse(["status" => $status]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createEmployeeStatus(Request $request) {
        try {
            $request->validate([
                "type" => "required",
                "description" => "required|unique:employee_status",
                "status" => "required"
            ]);

            EmployeeStatus::create([
                "type" => $request->type,
                "description" => $request->description,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Employee status saved successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function deleteEmployeeStatus(Request $request, $id) {
        try {
            $status = EmployeeStatus::find($id);
            $status->delete();
 
            return $this->sendResponse(["message" => "Employee status deleted successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editEmployeeStatus(Request $request, $id) {
        try {
            $request->validate([
                "type" => "required",
                "description" => "required|unique:employee_status,description,except,".$id,
                "status" => "required"
            ]);

            EmployeeStatus::where("id", $id)->update([
                "type" => $request->type,
                "description" => $request->description,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Employee status saved successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
