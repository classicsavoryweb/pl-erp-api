<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\ScheduleMasterlistExport;
use App\Http\Controllers\Controller;
use App\Models\ScheduleMasterlist;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class ScheduleMasterlistController extends Controller
{
    public function getAllScheduleMasterlist(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $schedule_masterlist = ScheduleMasterlist::query();
            if ($request->has(["column_type", "key_word"])) {
                $schedule_masterlist = $schedule_masterlist->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["export"]) && $request->export) {
                $schedule_masterlist = $schedule_masterlist->get();
                return Excel::download(new ScheduleMasterlistExport($schedule_masterlist), "schedule_masterlist.xls");
            }

            if ($request->has(["status"])) {
                $schedule_masterlist = $schedule_masterlist->where("status", $request->status);
            }

            $schedule_masterlist = $schedule_masterlist->paginate($perPage);
            return $this->sendResponse($schedule_masterlist);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportScheduleMasterlist(Request $request) {
        $request["export"] = true;

        return $this->getAllScheduleMasterlist($request);
    }

    public function getScheduleMasterlist(Request $request) {
        try {
            $schedule_masterlist = ScheduleMasterlist::where("status", "active")->get();
            return $this->sendResponse(["schedule_masterlist" => $schedule_masterlist]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createScheduleMasterlist(Request $request) {
        try {
            $request->validate([
                "start_time" => "required",
                "end_time" => "required",
                "selection" => "required",
                "classification" => "required",
                "time_break" => "required",
                "total_hours" => "required",
                "days" => "required",
                "required" => "required",
                "status" => "required",
            ]);

            ScheduleMasterlist::create([
                "start_time" => $request->start_time,
                "end_time" => $request->end_time,
                "selection" => $request->selection,
                "classification" => $request->classification,
                "time_break" => $request->time_break,
                "total_hours" => $request->total_hours,
                "days" => $request->days,
                "required" => $request->required,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Schedule created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editScheduleMasterlist(Request $request, $id) {
        try {
            $request->validate([
                "start_time" => "required",
                "end_time" => "required",
                "selection" => "required",
                "classification" => "required",
                "time_break" => "required",
                "total_hours" => "required",
                "days" => "required",
                "required" => "required",
                "status" => "required",
            ]);

            ScheduleMasterlist::where("id", $id)->update([
                "start_time" => $request->start_time,
                "end_time" => $request->end_time,
                "selection" => $request->selection,
                "classification" => $request->classification,
                "time_break" => $request->time_break,
                "total_hours" => $request->total_hours,
                "days" => $request->days,
                "required" => $request->required,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Schedule updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
