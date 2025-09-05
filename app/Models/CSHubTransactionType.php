<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        "status",
    ];
}
