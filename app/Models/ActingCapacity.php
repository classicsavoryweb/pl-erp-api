<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ActingCapacity extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "acting_capacity";

    protected $fillable = [
        "employee_id",
        "branch_id",
        "current_position_id",
        "recommended_position_id",
        "start_date",
        "end_date",
        "remarks",
        "period",
        "bond_amount",
        "effectivity_date",
        "status",
        "generated_by",
        "generated_date",
        "confirmed_date",
        "confirmed_by",
        "rejected_reason",
        "rejected_date",
        "rejected_by",
        "recommendation_letter",
        "bond_year",
        "oic_employee_number",
        "branch_assignment_id"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function attachments() {
        return $this->hasMany(ActingCapacityAttachments::class, "acting_capacity_id", "id");
    }

    public function current_position() {
        return $this->hasOne(Position::class, "id", "current_position_id");
    }

    public function recommended_position() {
        return $this->hasOne(Position::class, "id", "recommended_position_id");
    }

    public function extensions() {
        return $this->hasMany(ActingCapacityExtension::class, "acting_capacity_id", "id");
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }

    public function branch_assignment() {
        return $this->hasOne(Branch::class, "id", "branch_assignment_id");
    }
    public function generate() {
        return $this->hasOne(Employee::class, "id", "generated_by");
    }

    public function reject() {
        return $this->hasOne(Employee::class, "id", "rejected_by");
    }

    public function confirm() {
        return $this->hasOne(Employee::class, "id", "confirmed_by");
    }

    public function oic() {
        return $this->hasOne(Employee::class, "employee_number", "oic_employee_number");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Acting Position')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
