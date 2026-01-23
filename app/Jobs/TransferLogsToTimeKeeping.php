<?php

namespace App\Jobs;

use App\Models\BiotimeLog;
use App\Models\Holiday;
use App\Models\Overtime;
use App\Models\ScheduleMasterlist;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Http\Services\TimeKeepingService;
use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\NotifiesJobStatus;
use Carbon\Carbon;
use Log;
use Exception;
use DB;
class TransferLogsToTimeKeeping implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    protected $branchId;
    protected $cutOff;
    protected $timeKeepingService;
    protected $userId;
    protected $suppressNotification = false;

    public function __construct(string $branchId, $cutoff, ?int $userId = null, bool $suppressNotification = false)
    {
        $this->branchId = $branchId;
        $this->cutOff = $cutoff;
        $this->timeKeepingService = new TimeKeepingService();
        $this->userId = $userId;
        $this->suppressNotification = $suppressNotification;
    }

    public function handle(): void
    {
        try {
            DB::beginTransaction();

            $schedules = ScheduleTime::with(["employee"])
            ->where("branch_id", $this->branchId)
            ->whereBetween("date", $this->cutOff)
            ->get();

            foreach($schedules as $actualSchedule) {
                $employee = $actualSchedule->employee;

                if ($employee->type === "Agency") {
                    continue;
                }

                $log = BiotimeLog::with([
                    "punches" => function ($q) {
                        $q->orderBy('punch_time', 'ASC');
                    },
                ])->where("date", $actualSchedule->date)
                ->where("employee_id", $employee->id)
                ->first();

                if (isset($log)) {
                    $bioTimePunches = $log->punches;
                    $this->timeKeepingService->computeTimeKeeping(
                        $this->branchId,
                        $log,
                        $bioTimePunches,
                        $actualSchedule,
                        $employee,
                        new TimeKeeping(),
                        false,
                        "timekeeping"
                    );
                } else {
                    $isHoliday = false;
                    $holidayHours = $employee->schedule->schedule_type->name === "Compressed Schedule" ? 9.5 : 8;
                    $holiday = Holiday::where("date", $actualSchedule->date)
                    ->whereHas("branches", function ($query) {
                        return $query->where("branch_id", $this->branchId);
                    })
                    ->first();
                    $holidayType = "SH";
                    $isRequiredToReport = false;

                    if ($holiday) {
                        $isHoliday = true;
                        $branch = Branch::find($this->branchId);
                        $holidayType = $holiday->holiday_type;

                        if ($holiday->required_for === "BOTH" || $holiday->required_for === strtoupper($branch->type)) {
                            $isRequiredToReport = true;
                        }
                    }

                    $existing = TimeKeeping::where("date", $actualSchedule->date)
                    ->where("employee_id", $employee->id)
                    ->get();

                    if ($existing) {
                        foreach($existing as $t) {
                            $t->delete();
                        }
                    }

                    if ($actualSchedule->timetable == "INCENTIVE LEAVE") {
                        TimeKeeping::create([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "biotime_log_id" => null,
                            "schedule_time_id" => $actualSchedule->id,
                            "date" => $actualSchedule->date,
                            "worked_hours" => 0,
                            "legal_holiday" => 0,
                            "special_holiday" => 0,
                            "regular_overtime" => 0,
                            "tardiness" => 0,
                            "night_diff_hours" => 0,
                            "night_diff_overtime" => 0,
                            "legal_holiday_hours" => 0,
                            "legal_holiday_overtime" => 0,
                            "special_holiday_hours" => 0,
                            "special_holiday_overtime" => 0,
                            "undertime" => 0,
                            "is_cancelled_off" => false,
                            "branch_id" => $this->branchId,
                            "final_worked_hours" => 0
                        ]);
                    }
                    
                    if (
                        ($actualSchedule->timetable == "NO SCHEDULE" || $actualSchedule->timetable == "SUSPENSION" || $actualSchedule->timetable == "REST DAY" || $actualSchedule->timetable == "LEAVE") &&
                        $employee->current_position->timekeeping_type === "Daily" &&
                        $employee->current_branch->type === "Branch" &&
                        // !$isRequiredToReport &&
                        $isHoliday &&
                        $holidayType == "LH"
                    ) {
                        TimeKeeping::create([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "biotime_log_id" => null,
                            "schedule_time_id" => $actualSchedule->id,
                            "date" => $actualSchedule->date,
                            "worked_hours" => 0,
                            "legal_holiday" => $holidayType == "LH" ? "Yes" : "No",
                            "special_holiday" => $holidayType == "SH" ? "Yes" : "No",
                            "regular_overtime" => 0,
                            "tardiness" => 0,
                            "night_diff_hours" => 0,
                            "night_diff_overtime" => 0,
                            "legal_holiday_hours" => $holidayType == "LH" ? $holidayHours : 0,
                            "legal_holiday_overtime" => 0,
                            "special_holiday_hours" => $holidayType == "SH" ? $holidayHours : 0,
                            "special_holiday_overtime" => 0,
                            "undertime" => 0,
                            "is_cancelled_off" => false,
                            "branch_id" => $this->branchId,
                            "final_worked_hours" => 0
                        ]);
                    }

                    if (
                        $actualSchedule->timetable == "NO SCHEDULE" &&
                        $isHoliday &&
                        !$isRequiredToReport &&
                        $employee->schedule->schedule_type->name === "Compressed Schedule"
                    ) {
                        TimeKeeping::create([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "biotime_log_id" => null,
                            "schedule_time_id" => $actualSchedule->id,
                            "date" => $actualSchedule->date,
                            "worked_hours" => 0,
                            "legal_holiday" => $holidayType == "LH" ? "Yes" : "No",
                            "special_holiday" => $holidayType == "SH" ? "Yes" : "No",
                            "regular_overtime" => 0,
                            "tardiness" => 0,
                            "night_diff_hours" => 0,
                            "night_diff_overtime" => 0,
                            "legal_holiday_hours" => $holidayType == "LH" ? $holidayHours : 0,
                            "legal_holiday_overtime" => 0,
                            "special_holiday_hours" => $holidayType == "SH" ? $holidayHours : 0,
                            "special_holiday_overtime" => 0,
                            "undertime" => 0,
                            "is_cancelled_off" => false,
                            "branch_id" => $this->branchId,
                            "final_worked_hours" => 0
                        ]);
                    }
                    
                    $sched = ["NO SCHEDULE","SUSPENSION", "REST DAY", "LEAVE", "INCENTIVE LEAVE"];

                    if (!in_array($actualSchedule->timetable ,$sched)) {
                        TimeKeeping::create([
                            "employee_id" => $employee->id,
                            "biometric_number" => $employee->biometric_number,
                            "biotime_log_id" => null,
                            "schedule_time_id" => $actualSchedule->id,
                            "date" => $actualSchedule->date,
                            "worked_hours" => 0,
                            "legal_holiday" => $holidayType == "LH" ? "Yes" : "No",
                            "special_holiday" => $holidayType == "SH" ? "Yes" : "No",
                            "regular_overtime" => 0,
                            "tardiness" => 0,
                            "night_diff_hours" => 0,
                            "night_diff_overtime" => 0,
                            "legal_holiday_hours" => 0,
                            "legal_holiday_overtime" => 0,
                            "special_holiday_hours" => 0,
                            "special_holiday_overtime" => 0,
                            "undertime" => 0,
                            "is_cancelled_off" => false,
                            "branch_id" => $this->branchId,
                            "final_worked_hours" => 0
                        ]);
                    }
                }
            }
            
            $timeKeepings = TimeKeeping::where("branch_id", $this->branchId)
            ->whereBetween("date", $this->cutOff)
            ->get();

            foreach($timeKeepings as $timeKeep) {
                $actualSchedule = $timeKeep->schedule;
                $this->timeKeepingService->computePayment($timeKeep, $actualSchedule);
            }

            $this->notifyJobFinished();
            DB::commit();
        } catch (Exception $e) {
            Log::error($e);
            DB::rollBack();
            $this->notifyJobFailed();
        }
    }

    public function getCutOffForDate($date){
        $carbonDate = Carbon::parse($date);
        $day = $carbonDate->day;

        $cutOff1 = [11,12,13,14,15,16,17,18,19,20,21,22,23,24,25];
        $cutOff2 = [26,27,28,29,30,31,1,2,3,4,5,6,7,8,9,10];
        if (in_array($day, $cutOff2)) {
            if ($day <= 10) { 
                $startTime = $carbonDate->copy()->setDay(26)->subMonth()->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->endOfDay();
            } else {
                $startTime = $carbonDate->copy()->setDay(26)->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->addMonth()->endOfDay();
            }
        } else {
            $startTime = $carbonDate->copy()->setDay(11)->startOfDay();
            $endTime = $carbonDate->copy()->setDay(25)->endOfDay();
        }

        return [
            'start_time' => $startTime->toDateString(),
            'end_time' => $endTime->toDateString(),
        ];
    }
    public function formatHours($hours) {
        $formattedHours = $hours;

        if ($formattedHours < 1 && $formattedHours > 0) {
            $formattedHours = .5;
        } else {
            $formattedHours = floor($hours / 0.5) * 0.5;
        }   
        return $formattedHours;
    }
}
