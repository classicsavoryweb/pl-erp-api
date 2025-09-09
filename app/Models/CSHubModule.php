<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\CSHubTransactionType;
use Illuminate\Database\Eloquent\Model;

class CSHubModule extends Model
{
    use HasFactory;

    protected $table = "cs_hub_modules";

    protected $fillable = [
        "description",
        "status",
    ];

    public function cs_hub_transaction_type() {
        return $this->belongsTo(CSHubTransactionType::class);
    }
}
