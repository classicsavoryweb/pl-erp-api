<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankDetail extends Model
{
    use HasFactory;
    protected $fillable = ["employee_id", "bank_number","bank_account_type_id" ,"remarks"];
    protected $table = "bank_details";

    public function bank_account_type() {
        return $this->hasOne(BankAccountType::class, "id", "bank_account_type_id");
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}
