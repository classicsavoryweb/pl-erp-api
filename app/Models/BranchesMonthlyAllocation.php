<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchesMonthlyAllocation extends Model
{
    use HasFactory;

    protected $table = "branches_monthly_allocation";
    protected $fillable = [
        "thirteenth_month_file_id",
        "branch_id",
        "branch_net_claim_13th_month",
        "branch_net_claim_pay_leave",
        "branch_net_claim_additional",
        "branch_total"
    ];

    public function branches() {
        return $this->belongsTo(Branch::class, "branch_id", "id");
    }
}
