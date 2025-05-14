<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccountType extends Model
{
    use HasFactory;
    protected $fillable = ["description", "number_length", "status"];
    protected $table = "bank_account_type";

    public function bank_detail() {
        return $this->belongsTo(BankDetail::class);
    }
}
