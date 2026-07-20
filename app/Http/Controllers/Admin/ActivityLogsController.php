<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use Spatie\Activitylog\Models\Activity;
class ActivityLogsController extends Controller
{
    public function getAllActivityLogs(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $activityLogs = Activity::with('causer')
            ->whereNot("causer_id", null)
            ->orderBy("created_at", "desc")
            ->paginate($perPage);

            return $this->sendResponse($activityLogs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getSpecificActivityLogs(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $request->validate([
                "subject_id" => "nullable",
                "log_name" => "required"
            ]);

            $activityLogs = Activity::with('causer')
            ->when($request->filled("subject_id"), function ($query) use ($request) {
                $query->where("subject_id", $request->subject_id);
            })
            ->where("log_name", $request->log_name)
            ->orderBy("created_at", "desc")
            ->paginate($perPage);

            return $this->sendResponse($activityLogs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
