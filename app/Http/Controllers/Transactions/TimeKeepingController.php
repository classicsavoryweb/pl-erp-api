<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Jobs\TransferLogsToTimeKeeping;
use App\Models\BiotimeLog;
use App\Models\TimeKeeping;
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
            $attendance = BiotimeLog::whereHas("employee", function($query) use ($request) {
                $query->where("branch_id", $request->branch_id);
            })
            ->whereBetween("date", $currentCutOff)
            ->where("is_transferred", false)
            ->get();

            if ($attendance->count() > 0) {
                TransferLogsToTimeKeeping::dispatch($request->branch_id);
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
                "branch_id" => "required"
            ]);
            
            $perPage = $request->perPage ?? 10;
            $currentCutOff = $this->getCurrentCutoff();

            $timeKeeping = TimeKeeping::with(["employee"])
            ->whereHas("employee", function ($query) use($request) {
                $query->where("branch_id", $request->branch_id);
            })
            ->whereBetween("date", $currentCutOff)
            ->paginate($perPage);
            return $this->sendResponse($timeKeeping);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
