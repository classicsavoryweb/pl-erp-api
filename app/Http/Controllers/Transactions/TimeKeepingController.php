<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTimekeepingSummary;
use App\Jobs\TransferLogsToTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\Employee;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Log;

class TimeKeepingController extends Controller
{
    public function generateTimeKeeping(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required"
            ]);

            $currentCutOff = $this->getCurrentCutoff();
            $year = $request->year ?? Carbon::now()->year;

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $attendance = BiotimeLog::whereHas("employee", function($query) use ($request) {
                $query->where("branch_id", $request->branch_id);
            })
            ->whereBetween("date", [$start_time, $end_time])
            ->where("is_transferred", false)
            ->get();

            if ($attendance->count() > 0) {
                TransferLogsToTimeKeeping::dispatch($request->branch_id, [$start_time, $end_time]);
                return $this->sendResponse(["message" => "Logs are in queue for processing."]);
            } else {
                return $this->sendError(["message" => "Logs are missing or already generated." ]);
            }
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllTimeKeeping(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required",
                "cutoff" => "required",
                "year" => "required",
            ]);
            $perPage = $request->perPage ?? 10;
            $currentCutOff = $this->getCurrentCutoff();
            $year = $request->year ?? Carbon::now()->year;

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $timeKeeping = TimeKeeping::with(["employee"])
            ->whereHas("employee", function ($query) use($request) {
                $query->where("branch_id", $request->branch_id);
            })
            ->whereBetween("date", [$start_time, $end_time])
            ->paginate($perPage);

            return $this->sendResponse($timeKeeping);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllTimeKeepingSummary(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required",
                "cutoff" => "required",
                "year" => "required",
            ]);
            $perPage = $request->perPage ?? 10;
            $currentCutOff = $this->getCurrentCutoff();
            $year = $request->year ?? Carbon::now()->year;

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $timeKeeping = TimeKeepingSummary::with(["employee", "lates"])
            ->whereHas("employee", function ($query) use($request) {
                $query->where("branch_id", $request->branch_id);
            })
            ->where("cut-off", $start_time.'-'.$end_time)
            ->join("employees", "timekeeping_summary.employee_id", "=", "employees.id")
            ->orderBy("employees.last_name", "ASC")
            ->select("timekeeping_summary.*")
            ->paginate($perPage);

            return $this->sendResponse($timeKeeping);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function generateTimeKeepingSummary(Request $request) {
        try {
            $request->validate([
                "branch_id" => "required",
                "cutoff" => "required",
                "year" => "required",
            ]);

            $currentCutOff = $this->getCurrentCutoff();
            $year = $request->year ?? Carbon::now()->year;

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $cutOff = [
                "start_time" => $start_time,
                "end_time" => $end_time
            ];

            $employees = Employee::where("branch_id", $request->branch_id)
            ->where("status", "active")
            ->get();

            GenerateTimekeepingSummary::dispatch($employees, $cutOff);

            return $this->sendResponse(["message" => "Timekeeping are in queue for processing."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
