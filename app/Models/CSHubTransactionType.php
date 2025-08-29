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
        "ho_debit",
        "ho_credit",
        "branch_debit",
        "branch_credit",
        "status",
    ];
}
