<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LastPayDeduction extends Model
{
    use HasFactory;

    protected $table = "last_pay_deduction";

    protected $fillable = [
        "last_pay_id",
        "employee_id",
        "reference_doc",
        "deduction_id",
        "amount",
    ];

    public function last_pay() {
        return $this->belongsTo(LastPay::class, "last_pay_id", "id");
    }

    public function deduction() {
        return $this->hasOne(Deduction::class, "id", "deduction_id");
    }
}
