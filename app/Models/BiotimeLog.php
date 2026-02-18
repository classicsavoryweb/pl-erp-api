<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiotimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "biometric_number",
        "date",
        "remarks",
        "is_transferred",
        "is_modified",
        "status"
    ];

    protected $table = "biotime_logs";

    protected $casts = [
        "is_modified" => "boolean"
    ];

    public function updateStatusFromPunchesCount(?int $punches = null): void
    {
        $count = $punches ?? $this->punches()->count();
        if ($count === 4) {
            $this->status = "COMPLETE";
        } else if ($count > 4) {
            $this->status = "MULTIPLE LOGS";
        } else {
            $this->status = "INCOMPLETE";
        }

        if (method_exists($this, 'saveQuietly')) {
            $this->saveQuietly();
            return;
        }

        $this->save();
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function punches() {
        return $this->hasMany(BiotimePunch::class, "biotime_log_id", "id")->orderBy("punch_time", "asc");
    }
}
