<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    use HasFactory;

    protected $table = "entries";
    protected $fillable = [
        "entry_id",
        "pay_date",
        "document_no",
        "branch_id",
        "branch_number",
        "amount",
        "vendor_no",
        "account_no",
        "description",
        "balance_type",
        "is_block_payment",
        "status",
        "generated_at",
        "generated_by",
        "posted_at",
        "posted_by",
    ];

    public function branch() {
        return $this->hasOne(Branch::class,'id','branch_id');
    }
}
