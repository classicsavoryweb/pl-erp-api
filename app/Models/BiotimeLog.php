<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiotimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "biometric_number",
        "date",
        "remarks"
    ];

    protected $table = "biotime_logs";

    protected $appends = [
        "status"
    ];

    public function status(): Attribute {
        $punches = $this->punches()->count();
        $status = "";
        if ($punches == 4) {
            $status = "COMPLETE";
        } else if ($punches > 4) {
            $status = "MULTIPLE LOGS";
        } else if ($punches <= 3) {
            $status = "INCOMPLETE";
        }

        return Attribute::get(
            fn () => $status
        );
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function punches() {
        return $this->hasMany(BiotimePunch::class, "biotime_log_id", "id");
    }
}
