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

            $query = Activity::with('causer')->whereNot("causer_id", null)->orderBy("created_at", "desc");
         
            if ($request->filled("column_type") && $request->filled("key_word")) {
                if ($request->column_type === "causer.full_name") {
                    $query->whereHas("causer", function ($q) use ($request) {
                        $keyword = $request->key_word;
                        $q->where(function ($q) use ($keyword) {
                            $q->where("first_name", "LIKE", "%{$keyword}%")
                                ->orWhere("last_name", "LIKE", "%{$keyword}%")
                                ->orWhere("middle_name", "LIKE", "%{$keyword}%")
                                ->orWhereRaw(
                                    "CONCAT(last_name, ', ', first_name, ' ', middle_name) LIKE ?",
                                    ["%{$keyword}%"]
                                );
                        });
                    });
                } else {
                $query->where(
                    $request->column_type,
                    "LIKE",
                    "%" . $request->key_word . "%"
                );
                }
            }
            $activityLogs = $query->paginate($perPage);
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
