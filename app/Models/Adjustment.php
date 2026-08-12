<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
    use HasFactory;

    protected $table = "adjustments";
    protected $fillable = [
        "employee_id",
        "biometric_number",
        "date",
        "days",
        "cancelled_rd",
        "taxable_amount",
        "amount_paid",
        "correct_amount_paid",
        "actual_schedule",
        "type",
        "status",
        "remarks",
        "added_by",
        "added_at",
        "approved_by",
        "approved_at",
        "unapproved_by",
        "unapproved_at",
        "timekeeping_summary_id",
        "is_timekeeping_locked",
        "cut_off"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function timekeeping_summary() {
        return $this->belongsTo(TimeKeepingSummary::class, "timekeeping_summary_id", "id");
    }

    public function added_by_user() {
        return $this->hasOne(User::class, "id", "added_by");
    }

}
