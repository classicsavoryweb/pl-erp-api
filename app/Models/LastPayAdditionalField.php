<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LastPayAdditionalField extends Model
{
    use HasFactory;

    protected $table = "last_pay_additional_fields";
    protected $fillable = [
        "last_pay_id",
        "transaction_type_id",
        "amount"
    ];

    public function last_pay() {
        return $this->belongsTo(LastPay::class, "id", "last_pay_id");
    }

    public function transaction_type() {
        return $this->hasOne(CSHubTransactionType::class, "id", "transaction_type_id");
    }
}
