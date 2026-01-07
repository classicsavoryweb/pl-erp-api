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
        "month",
        "type",
        "file_name",
        "file_path",
        "generated_by",
    ];

    public function user() {
        return $this->belongsTo(User::class, "generated_by", "id");
    }

    public function thirteenth_month() {
        return $this->belongsTo(ThirteenthMonthPay::class, "id", "thirteenth_month_file_id");
    }
}
