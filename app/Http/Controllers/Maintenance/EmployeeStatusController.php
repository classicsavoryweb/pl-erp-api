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
}
