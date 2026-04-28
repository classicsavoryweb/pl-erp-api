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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Carbon\Carbon;
use Log;
use Exception;
use DB;
class TransferLogsToTimeKeeping implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    // public $tries = 3;
    // public $timeout = 3600;

    protected $branchId;
    protected $cutOff;
    protected $timeKeepingService;
    protected $userId;
    protected $suppressNotification = false;
    protected $employeeId;
    protected $dates;

    public function __construct(
        string $branchId,
        $cutoff,
        ?int $userId = null,
        bool $suppressNotification = false,
        ?int $employeeId = null,
        ?array $dates = null
    )
    {
        $this->branchId = $branchId;
        $this->cutOff = $cutoff;
        $this->timeKeepingService = new TimeKeepingService();
        $this->userId = $userId;
        $this->suppressNotification = $suppressNotification;
        $this->employeeId = $employeeId;
        $this->dates = $dates;
    }

    public function middleware() {
        return [
            (new WithoutOverlapping("timekeeping_branch_{$this->branchId}"))
                ->shared()
                ->releaseAfter(1200)
                ->expireAfter(7200)
        ];
    }

    public function handle(): void
    {
        try {
            DB::beginTransaction();

            $schedulesQuery = ScheduleTime::with([
                "employee",
                "employee.schedule",
                "employee.schedule.schedule_type",
                "employee.current_position"
            ])
            ->where("branch_id", $this->branchId)
            ->whereBetween("date", $this->cutOff);

            if (!empty($this->employeeId)) {
                $schedulesQuery->where("employee_id", $this->employeeId);
            }
            if (!empty($this->dates)) {
                $schedulesQuery->whereIn("date", $this->dates);
            }

            $schedules = $schedulesQuery->get();

            foreach($schedules as $actualSchedule) {
                $employee = $actualSchedule->employee;
                $timekeepingType = $employee->current_position->timekeeping_type;

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

                if (isset($log) && $actualSchedule->timetable != "LEAVE") {
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

                    $holidayHours = $isRequiredToReport ? 0 : $holidayHours; 
                    $existing = TimeKeeping::where("date", $actualSchedule->date)
                    ->where("employee_id", $employee->id)
                    ->get();

                    if ($existing) {
                        foreach($existing as $t) {
                            $t->delete();
                        }
                    }

                    TimeKeeping::create([
                        "employee_id" => $employee->id,
                        "biometric_number" => $employee->biometric_number,
                        "biotime_log_id" => null,
                        "schedule_time_id" => $actualSchedule->id,
                        "date" => $actualSchedule->date,
                        "worked_hours" => 0,
                        "legal_holiday" => $isHoliday && $holidayType == "LH" ? "Yes" : "No",
                        "special_holiday" => $isHoliday && $holidayType == "SH" ? "Yes" : "No",
                        "regular_overtime" => 0,
                        "tardiness" => 0,
                        "night_diff_hours" => 0,
                        "night_diff_overtime" => 0,
                        "legal_holiday_hours" => $isHoliday && $holidayType == "LH" ? $holidayHours : 0,
                        "legal_holiday_overtime" => 0,
                        "special_holiday_hours" => $isHoliday && $holidayType == "SH" ? $holidayHours : 0,
                        "special_holiday_overtime" => 0,
                        "undertime" => 0,
                        "is_cancelled_off" => false,
                        "branch_id" => $this->branchId,
                        "final_worked_hours" => 0
                    ]);
                }
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
