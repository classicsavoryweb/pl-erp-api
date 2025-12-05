<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchAdditionalIncome extends Model
{
    use HasFactory;
    protected $table = "branch_additional_income";
    protected $fillable = [
        "branch_id",
        "amount",
        "pay_date",
    ];

    public function branch() {
        return $this->belongsTo(Branch::class, "branch_id", "id");
    }
}
