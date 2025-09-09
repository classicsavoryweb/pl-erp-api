<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\CSHubTransactionType;
use Illuminate\Database\Eloquent\Model;

class CSHubSourceType extends Model
{
    use HasFactory;

    protected $table = "cs_hub_source_type";

    protected $fillable = [
        "description",
        "source_type",
        "status",
    ];

    public function cs_hub_transaction_type() {
        return $this->belongsTo(CSHubTransactionType::class);
    }
}
