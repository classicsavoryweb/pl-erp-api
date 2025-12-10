<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LastPay extends Model
{
    use HasFactory;

    protected $table = "last_pay";

    protected $fillable = [
        "last_pay_number",
        "employee_id",
        "payroll_hold",
        "thirteenth_month",
        "year",
        "sl_vl",
        "tax_refund",
        "incentives",
        "total_deductions",
        "sales",
        "meal",
        "last_pay",
        "status"
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            $model->update([
                'last_pay_number' => str_pad($model->id, 8, '0', STR_PAD_LEFT)
            ]);
        });
    }

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }

    public function deductions() {
        return $this->hasMany(LastPayDeduction::class, "last_pay_id", "id");
    }
}
