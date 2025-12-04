<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CSHubModule;
use App\Models\CSHubSourceType;
use App\Models\Deduction;

class CSHubTransactionType extends Model
{
    use HasFactory;

    protected $table = "cs_hub_transaction_type";

    protected $fillable = [
        "description",
        "source_id",
        "transaction_type",
        "exam_type",
        "deduction_id",
        "deduction_status_positive",
        "deduction_status_negative",
        "credit_status_positive",
        "credit_status_negative",
        "email_status",
        "ho_debit",
        "ho_credit",
        "branch_debit",
        "branch_credit",
        "advances_debit",
        "is_block_payment",
        "status",
    ];

    public function module() {
        return $this->hasOne(CSHubModule::class,'id','source_id');
    }

    public function deduction() {
        return $this->hasOne(Deduction::class,'id','deduction_id');
    }

    public function trans_type() {
        return $this->hasOne(CSHubSourceType::class,'id','transaction_type');
    }

    public function unique_exam_type() {
        return $this->hasOne(CSHubSourceType::class,'id','exam_type');
    }

    public function isDeduction() {
        return $this->deduction_status_positive == "Yes";
    }

    public function isCredit() {
        return $this->credit_status_positive == "Yes";
    }
}
