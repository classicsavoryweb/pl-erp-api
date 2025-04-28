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

            $status = EmployeeStatus::paginate($perPage);
            return $this->sendResponse($status);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function createEmployeeStatus(Request $request) {
        try {
            $request->validate([
                "type" => "required",
                "description" => "required"
            ]);

            EmployeeStatus::create([
                "type" => $request->type,
                "description" => $request->description
            ]);

            return $this->sendResponse(["message" => "Employee status saved successfully"]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function deleteEmployeeStatus(Request $request, $id) {
        try {
            $status = EmployeeStatus::find($id);
            $status->delete();
 
            return $this->sendResponse(["message" => "Employee status deleted successfully"]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function editEmployeeStatus(Request $request, $id) {
        try {
            EmployeeStatus::where("id", $id)->update([
                "type" => $request->type,
                "description" => $request->description
            ]);

            return $this->sendResponse(["message" => "Employee status saved successfully"]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }
}
