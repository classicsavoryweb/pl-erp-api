<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScheduledTimekeepingGenerationJob;
use App\Models\Cutoff;
use App\Models\ScheduledTimekeepingGeneration;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleTimekeepingController extends Controller
{
    public function getAllScheduledTimeKeeping (Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $schedules = ScheduledTimekeepingGeneration::with([
                "user",
                "cut_off"
            ])
            ->orderBy("created_at", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($schedules);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createScheduleTimeKeepingGeneration(Request $request) {
        try {
            $request->validate([
                "schedule" => "required",
            ]);

            $activeCutOff = Cutoff::where("status", "active")->first();

            if ($activeCutOff->is_cutoff_locked) {
                return $this->sendError(["message" => "Cutoff already finalized, please set a new cutoff."]);
            }

            $scheduleCreated = ScheduledTimekeepingGeneration::create([
                "schedule" => $request->schedule,
                "generated_by" => Auth::user()->id,
                "cut_off_id" => $activeCutOff->id,
                "status" => "Waiting"
            ]);

            ScheduledTimekeepingGenerationJob::dispatch($activeCutOff->id, $scheduleCreated->id)
            ->delay(Carbon::parse($request->schedule, "Asia/Manila"));
            
            return $this->sendResponse(["message" => "Scheduled timekeeping generation created successfully."]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
