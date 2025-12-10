<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirteenthMonthFiles extends Model
{
    use HasFactory;

    protected $table = "thirteenth_month_files";
    protected $fillable = [
        "year",
        "type",
        "file_name",
        "file_path",
        "generated_by",
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "generated_by", "id");
    }

    public function thirteenth_month() {
        return $this->belongsTo(ThirteenthMonthPay::class, "id", "thirteenth_month_file_id");
    }
}
