<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "employee_number",
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
        "tin",
        "sss",
        "philhealth",
        "pagibig",
        "referred_by",
        "remarks",
        "date_hired",
        "employee_status_id",
        "position_id",
        "station_id",
        "branch_id",
        "home_branch_id",
        "type",
        "agency_id"
    ];

    protected $table = "employees";

    protected $appends = ['full_name'];


    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn () => "{$this->last_name}, {$this->first_name} {$this->middle_name}"
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function status() {
        return $this->hasOne(EmployeeStatus::class,'id','employee_status_id');
    }
    public function position() {
        return $this->hasOne(Position::class,'id','position_id');
    }

    public function branch() {
        return $this->hasOne(Branch::class,'id','branch_id');
    }

    public function station() {
        return $this->hasOne(Station::class,'id','station_id');
    }

    public function agency() {
        return $this->hasOne(Station::class,'id','agency_id');
    }
}
