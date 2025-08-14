<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ScheduleDetail;
use App\Models\ScheduleType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where("status", "active")->where("type", "direct")->get();

        foreach($employees as $employee) {
            $schedule = "Others";
            if ( strtolower($employee->station->name) === "head office") {
                if (in_array($employee->current_position->description, ['Stockman', 'Technician', 'Driver', 'Repair & Maintenance Staff', "Janitor/Maintenance Staff"])) {
                    $schedule = "Normal Schedule";
                } else {
                    $schedule = "Compressed Schedule";
                } 
            } else {
                $schedule = "Normal Schedule";
            }

            $scheduleType = ScheduleType::where("name", $schedule)->first();
            ScheduleDetail::updateOrCreate([
                "employee_id" => $employee->id,
                "schedule_type_id" => $scheduleType->id
            ]);
        }

        $jsonPath = public_path('jsons/biometrics/flexi_time.json');
        $jsonData = file_get_contents($jsonPath);
        $flexi = json_decode($jsonData, true);

        foreach($flexi['tbl_bio_flexytime'] as $flex) {
            $employee = Employee::where("biometric_number", $flex['employee_id2'])->first();
            if ($employee) {
                ScheduleDetail::where("employee_id", $employee->id)->update([
                    "flexible_schedule" => "Yes"
                ]);
            }
        }
        
        $jsonPath = public_path('jsons/biometrics/incentive_leave.json');
        $jsonData = file_get_contents($jsonPath);
        $flexi = json_decode($jsonData, true);

        foreach($flexi['tbl_bio_withil'] as $flex) {
            $employee = Employee::where("biometric_number", $flex['employee_id2'])->first();
            if ($employee) {
                ScheduleDetail::where("employee_id", $employee->id)->update([
                    "incentive_leave" => "Yes"
                ]);
            }
        }  
    }
}
