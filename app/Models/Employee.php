<?php

namespace App\Models;

use App\Models\Accounting\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;
use Exception;

class Employee extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "image",
        "employee_number",
        "biometric_number",
        "first_name",
        "middle_name",
        "last_name",
        "email",
        "civil_status",
        "gender",
        "height",
        "birthday",
        "birthplace",
        "address",
        "provincial_address",
        "region",
        "contact_number",
        "tax_type",
        "tin",
        "sss",
        "philhealth",
        "pagibig",
        "referred_by",
        "remarks",
        "date_hired",
        "regularization_date",
        "separation_date",
        "separation_reason",
        "employee_status_id",
        "education_history_id",
        "position_id",
        "station_id",
        "branch_id",
        "home_branch_id",
        "type",
        "agency_id",
        "emergency_contact_name",
        "emergency_contact_number",
        "emergency_contact_address",
        "emergency_contact_relationship",
        "status",
        "oic_status",
        "employee_number_2"
    ];

    protected $table = "employees";

    protected $appends = [
        'full_name',
        "position",
        "proper_full_name",
        "formal_full_name",
        "age",
        "monthly_salary",
        "daily_salary",
        "cut_off_base_salary",
    ];

    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn () => "{$this->last_name}, {$this->first_name} {$this->middle_name}"
        );
    }

    protected function monthlySalary(): Attribute
    {
        $monthlySalary = 0;

        $scheduleType = $this->schedule->schedule_type->name ?? "N/A";
        $salaryPosition = $this->current_position->salary;
        $rateCode = $this->home_branch->rate_code;

        try {
            if ($salaryPosition->rate_category === "MINIMUM") {
                $minimumRate = Rate::where("code", $rateCode)->first();

                $monthlySalary = $minimumRate->minimum * 26;
            } else {
                $monthlySalary = $salaryPosition->basic_salary;
            }

            return Attribute::get(
                fn () => $monthlySalary
            );
        } catch (Exception $e) {
            return Attribute::get(
                fn () => $monthlySalary
            );
        }
    }

    protected function cutOffBaseSalary(): Attribute
    {
        $salary = 0;
        $salaryPosition = $this->current_position->salary;
        $rateCode = $this->home_branch->rate_code;

        try {
            if ($salaryPosition->rate_category === "MINIMUM") {
                $minimumRate = Rate::where("code", $rateCode)->first();

                $salary = ($minimumRate->minimum * 26) / 2;
            } else {
                $salary = $salaryPosition->basic_salary / 2;
            }

            return Attribute::get(
                fn () => $salary
            );
        } catch (Exception $e) {
            return Attribute::get(
                fn () => $salary
            );
        }
    }

    protected function dailySalary(): Attribute
    {
        $dailySalary = 0;

        $scheduleType = $this->schedule->schedule_type->name ?? "N/A";
        $salaryPosition = $this->current_position->salary;
        $rateCode = $this->home_branch->rate_code;
        try {
            if ($scheduleType === "Compressed Schedule") {
                if ($salaryPosition->rate_category === "MINIMUM") {
                    $minimumRate = Rate::where("code", $rateCode)->first();
    
                    $dailySalary = ($minimumRate->minimum * 26) / 22;
                } else {
                    $dailySalary = $salaryPosition->basic_salary / 22;
                }
            }
    
            if ($scheduleType === "Normal Schedule") {
                if ($salaryPosition->rate_category === "MINIMUM") {
                    $minimumRate = Rate::where("code", $rateCode)->first();
    
                    $dailySalary = $minimumRate->minimum;
                } else {
                    $dailySalary = $salaryPosition->basic_salary / 26;
                }
            }

            return Attribute::get(
                fn () => $dailySalary
            );
        } catch (Exception $e) {
            return Attribute::get(
                fn () => $dailySalary
            );
        }
    }

    protected function age(): Attribute
    {

        $now = Carbon::now();
        $birthday = Carbon::parse($this->birthday);
        $age = $now->diffInYears($birthday);

        return Attribute::get(
            fn () => $age
        );
    }

    protected function properFullName(): Attribute
    {
        $fullName = strtolower($this->first_name . ' ' . $this->last_name);
        if ($this->middle_name) {
            $middleInitial = strtoupper(substr($this->middle_name, 0, 1));
            $fullName = strtolower($this->first_name . ' ' . $middleInitial . '. ' . $this->last_name);
        }
        $properFullName = ucwords($fullName);
        return Attribute::get(
            fn () => $properFullName
        );
    }

    protected function formalFullName(): Attribute
    {
        $pronoun = $this->gender === "Male" ? "Mr." : "Ms.";
        $fullName = strtolower($this->first_name . '. ' . $this->last_name);

        if ($this->middle_name) {
            $middleInitial = strtoupper(substr($this->middle_name, 0, 1));
            $fullName = strtolower($this->first_name . ' ' . $middleInitial . '. ' . $this->last_name);
        }
        $properFullName = ucwords($fullName);
        return Attribute::get(
            fn () => $pronoun ." ". $properFullName
        );
    }

    protected function position(): Attribute
    {
        $position = $this->current_position->description;
        if ($this->oic_status === "OIC") {
            $position = $this->current_position->description." - ".$this->oic_status;
        }
        return Attribute::get(
            fn () => $position
        );
    }

    public function schedule() {
        return $this->hasOne(ScheduleDetail::class, "employee_id", "id");
    }

    public function employee_status() {
        return $this->hasOne(EmployeeStatus::class,'id','employee_status_id');
    }
    public function current_position() {
        return $this->hasOne(Position::class,'id','position_id');
    }

    public function current_branch() {
        return $this->hasOne(Branch::class,'id','branch_id');
    }

    public function home_branch() {
        return $this->hasOne(Branch::class,'id','home_branch_id');
    }

    public function station() {
        return $this->hasOne(Station::class,'id','station_id');
    }

    public function agency() {
        return $this->hasOne(Agency::class,'id','agency_id');
    }

    public function requirements() {
        return $this->hasOne(EmployeeRequirements::class, "employee_id", "id");
    }

    public function incentives_management() {
        return $this->hasOne(IncentivesManagement::class, "id", "employee_id");
    }

    public function contracts() {
        return $this->hasMany(Contract::class, 'employee_id','id');
    }

    public function education() {
        return $this->hasOne(EducationHistory::class, 'id','education_history_id');
    }

    public function dependents() {
        return $this->hasMany(Dependents::class, 'employee_id','id');
    }

    public function bank_details() {
        return $this->hasMany(BankDetail::class,'employee_id','id');
    }

    public function latest_bank_details() {
        return $this->hasOne(BankDetail::class,'employee_id','id')->latest("updated_at");
    }

    public function offenses() {
        return $this->hasMany(EmployeeOffense::class,'employee_id','id');
    }

    public function coe_requests() {
        return $this->hasMany(CoeRequest::class,'employee_id','id');
    }

    public function employee_deduction() {
        return $this->hasMany(EmployeeDeduction::class,'employee_id','id');
    }

    public function incentives() {
        return $this->hasMany(Incentives::class,'employee_id','id');
    }

    public function latest_contract()
    {
        return $this->hasOne(Contract::class)->latestOfMany();
    }

    public function latest_branch()
    {
        return $this->hasOne(BranchEmployee::class)->latestOfMany("date_assigned");
    }

    public function latest_position()
    {
        return $this->hasOne(EmployeePosition::class)->latestOfMany("date_started");
    }

    public function user() {
        return $this->belongsTo(User::class, "employee_number", "employee_number");
    }

    public function vendor() {
        return $this->belongsTo(Vendor::class, 'employee_number', 'ven_no');
    }

    public function positions() {
        return $this->belongsToMany(Position::class)
        ->using(EmployeePosition::class)
        ->withPivot('date_started', 'remarks', "oic_assignment", "date_appointed")
        ->withTimestamps();
    }

    public function branches() {
        return $this->belongsToMany(Branch::class)
        ->using(BranchEmployee::class)
        ->withPivot('date_assigned', 'remarks')
        ->withTimestamps();
    }

    public function trainings() {
        return $this->belongsToMany(TrainingTitle::class)
        ->using(EmployeeTrainings::class)
        ->withPivot('date', "score")
        ->withTimestamps();
    }

    public function vaccines() {
        return $this->belongsToMany(Vaccine::class)
        ->using(EmployeeVaccine::class)
        ->withPivot("dose", "status", "date")
        ->withTimestamps();
    }

    public function acting_capacities() {
        return $this->hasMany(ActingCapacity::class, "employee_id", "id");
    }

    public function attendance() {
        return $this->hasMany(BiotimeLog::class, "employee_id", "id");
    }

    public function timekeeping() {
        return $this->hasMany(TimeKeeping::class, "employee_id", "id");
    }

    public function adjustment_timekeeping() {
        return $this->hasMany(AdjustmentTimeKeeping::class, "employee_id", "id");
    }
    public function adjustments() {
        return $this->hasMany(Adjustment::class, "employee_id", "biometric_number");
    }

    public function overtime() {
        return $this->hasMany(Adjustment::class, "biometric_number", "biometric_number");
    }

    public function timekeeping_summary() {
        return $this->hasMany(TimeKeepingSummary::class, "employee_id", "id");
    }

    public function diagnosis() {
        return $this->belongsToMany(Diagnosis::class)
        ->using(EmployeeHealthSafety::class)
        ->withPivot(
            "consultation_date",
            "last_duty_date",
            "hso",
            "chief_complaint",
            "attending_physician",
            "physician_name",
            "swab_test",
            "swab_test_date",
            "swab_test_type",
            "swab_test_result",
            "return_to_work_date",
            "quarantine_period_from",
            "quarantine_period_to",
        )
        ->withTimestamps();
    }

    // public function getCutOffWorkDays($cutOff) {
    //     $days = 0;
    //     $schedules = ScheduleTime::where("employee_id", $this->id)
    //     ->whereBetween("date", [$cutOff["start_time"], $cutOff["end_time"]])
    //     ->get();

    //     $notCountedSchedule = ["NO SCHEDULE", "REST DAY", "LEAVE", "INCENTIVE_LEAVE"];
    //     foreach ($schedules as $schedule) {
    //         if (!in_array($schedule->timetable, $notCountedSchedule)) {
    //             $days += 1;
    //         }
    //     }

    //     return $days;
    // }

    public function getCutOffWorkDays($cutOff)
    {
        $notCountedSchedule = ["NO SCHEDULE", "REST DAY", "LEAVE", "INCENTIVE_LEAVE"];

        return ScheduleTime::where("employee_id", $this->id)
            ->whereBetween("date", [$cutOff["start_time"], $cutOff["end_time"]])
            ->whereNotIn("timetable", $notCountedSchedule)
            ->count();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                "employee_number",
                "biometric_number",
                "first_name",
                "middle_name",
                "last_name",
                "email",
                "civil_status",
                "gender",
                "height",
                "birthday",
                "birthplace",
                "address",
                "provincial_address",
                "region",
                "contact_number",
                "tax_type",
                "tin",
                "sss",
                "philhealth",
                "pagibig",
                "referred_by",
                "remarks",
                "date_hired",
                "regularization_date",
                "separation_date",
                "separation_reason",
                "employee_status_id",
                "employee_status.description",
                "education_history_id",
                "position_id",
                "current_position.description",
                "station_id",
                "branch_id",
                "current_branch.name",
                "home_branch_id",
                "home_branch.name",
                "type",
                "agency_id",
                "agency.name",
                "emergency_contact_name",
                "emergency_contact_number",
                "emergency_contact_address",
                "emergency_contact_relationship",
                "status",
            ])
            ->useLogName('Employee')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
