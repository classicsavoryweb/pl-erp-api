<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;

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

    protected $appends = ['full_name', "position", "proper_full_name", "formal_full_name", "age"];


    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn () => "{$this->last_name}, {$this->first_name} {$this->middle_name}"
        );
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
        $middleInitial = strtoupper(substr($this->middle_name, 0, 1));
        $fullName = strtolower($this->first_name . ' ' . $middleInitial . '. ' . $this->last_name);
        $properFullName = ucwords($fullName);
        return Attribute::get(
            fn () => $properFullName
        );
    }

    protected function formalFullName(): Attribute
    {
        $pronoun = $this->gender === "Male" ? "Mr." : "Ms.";
        $middleInitial = strtoupper(substr($this->middle_name, 0, 1));
        $fullName = strtolower($this->first_name . ' ' . $middleInitial . '. ' . $this->last_name);
        $properFullName = ucwords($fullName);
        return Attribute::get(
            fn () => $pronoun ." ". $properFullName
        );
    }

    protected function position(): Attribute
    {
        return Attribute::get(
            fn () => "{$this->current_position->description}-{$this->oic_status}"
        );
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

    public function offenses() {
        return $this->hasMany(EmployeeOffense::class,'employee_id','id');
    }

    public function coe_requests() {
        return $this->hasMany(CoeRequest::class,'employee_id','id');
    }

    public function latest_contract()
    {
        return $this->hasOne(Contract::class)->latestOfMany();
    }

    public function user() {
        return $this->belongsTo(User::class, "employee_number", "employee_number");
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
