<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CSHubModule extends Model
{
    use HasFactory;

    protected $table = "cs_hub_modules";

    protected $fillable = [
        "description",
        "status",
    ];
}
