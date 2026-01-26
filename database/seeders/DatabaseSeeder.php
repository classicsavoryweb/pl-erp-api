<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Http\Controllers\Admin\ActivityLogsController;
use App\Http\Services\TimeKeepingService;
use App\Models\BiotimeLog;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollList;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call(RoleSeeder::class);
        // $this->call(PermissionSeeder::class);
        // $this->call(AssignPermissionToAdminSeeder::class);
        // $this->call(AdminSeeder::class);
        // $this->call(HRRoleSeeder::class);
        // $this->call(MaintenanceSeeder::class);
        // $this->call(EmployeeRecordSeeder::class);
        // $this->call(EntrySeeder::class);

        // $timekeepingService = new TimeKeepingService();

        // $timeKeepings = TimeKeeping::where("date", ">", "2025-11-25")->get();

        // foreach ($timeKeepings as $timeKeep) {
        //     $schedule = ScheduleTime::where("employee_id", $timeKeep->employee_id)->where("date", $timeKeep->date)->first();
        //     $timekeepingService->computePayment($timeKeep, $schedule);
        // }

        // Incentive LEAVE
        // $employees = Employee::with(["current_branch","schedule"])->get();

        // foreach ($employees as $employee) {
        //     $incentiveLeave = "No";
        //     $flexi = "No";
        //     if ( in_array($employee->position_id, [12,86,139,53,63,136]) ) {
        //         $incentiveLeave = "Yes";
        //         $flexi = "Yes";
        //     }

        //     if ($employee->current_branch?->type == "Head Office") {
        //         $flexi = "Yes";
        //     }

        //     $employee->schedule->update([
        //         "incentive_leave" => $incentiveLeave,
        //         "flexible_schedule" => $flexi
        //     ]);
        // }

        // TimeKeeping::whereHas('employee', function ($query) {
        //     $query->where("type", "Agency");
        // })->delete();

        // TimeKeepingSummary::whereHas('employee', function ($query) {
        //     $query->where("type", "Agency");
        // })->delete();

        // $payrollList = PayrollList::with(["employee"])->get();

        // foreach ($payrollList as $list) {
        //     $employee = $list->employee;
        //     if ($employee->status == "inactive") {
        //         Log::info($employee->full_name);
        //         $date = Carbon::parse($employee->separation_date);
        //         $day = 11;

        //         if ($date->day >= 11 && $date->day <= 25) {
        //             $day = 25;
        //         }


        //         $date = Carbon::parse($employee->separation_date)->day($day)->startOfDay()->format("Y-m-d H:i:s");
        //         Log::info("SEPARATION DATE ". $employee->separation_date);
        //         Log::info("HOLDING DATE ". $date);

        //         // $date = Carbon::parse($employee->separation_date)->startOfDay()->format("Y-m-d H:i:s"); 
        //         $list->update([
        //             "holded_at" => $date,
        //             "is_hold" => true
        //         ]);
        //     } 

        // }

        $deductions = Activity::where("log_name", "Employee Deductions")
        ->where("event", "deleted")
        ->get();

        foreach ($deductions as $ded) {
            $array = json_decode($ded->properties, true);
            
            $data = collect($array["old"])->except(['id', "created_at", "updated_at"])->toArray();

            // Log::info($array["old"]);
            EmployeeDeduction::create($data);
        }
    }
}
