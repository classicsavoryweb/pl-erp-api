<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
