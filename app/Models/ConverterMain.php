<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverterMain extends Model
{
    use HasFactory;

    protected $table="converter_main";
    protected $fillable = [
        "batch_number",
        "total_amount",
        "count",
        "status",
        "remarks",
        "added_by",
        "posted_by",
        "generated_by",
        "voided_by",
        "posted_at",
        "generated_at",
        "voided_at",
    ];

    public function added_by() {
        return $this->hasOne(Employee::class, "id", "added_by");
    }

    public function posted_by() {
        return $this->hasOne(Employee::class, "id", "posted_by");
    }

    public function generated_by() {
        return $this->hasOne(Employee::class, "id", "generated_by");
    }

    public function voided_by() {
        return $this->hasOne(Employee::class, "id", "voided_by");
    }

    public function converter_details() {
        return $this->hasMany(ConverterDetail::class, "converter_main_id", "id");
    }
}
