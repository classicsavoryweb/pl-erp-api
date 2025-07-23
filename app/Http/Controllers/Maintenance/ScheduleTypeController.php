<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\ScheduleType;
use Illuminate\Http\Request;
use Exception;

class ScheduleTypeController extends Controller
{
    public function getAllScheduleTypes(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $scheduleType = ScheduleType::query();
            if ($request->has(["column_type", "key_word"])) {
                $scheduleType = $scheduleType->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $scheduleType = $scheduleType->where("status", $request->status);
            }

            $scheduleType = $scheduleType->paginate($perPage);
            return $this->sendResponse($scheduleType);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getScheduleTypes(Request $request) {
        try {
            $scheduleType = ScheduleType::where("status", "active")->get();
            return $this->sendResponse(["schedule_type" => $scheduleType]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createScheduleType(Request $request) {
        try {
            $request->validate([
                "name" => "required|unique:schedule_type",
                "status" => "required",
            ]);

            ScheduleType::create([
                "name" => $request->name,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Schedule type created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editScheduleType(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required|unique:schedule_type,name,".$id,
                "status" => "required",
            ]);

            ScheduleType::where("id", $id)->update([
                "name" => $request->name,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Schedule type updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
