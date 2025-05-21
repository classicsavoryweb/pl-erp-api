<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoeReason extends Model
{
    use HasFactory;

    protected $fillable = ["reason", "status"];
    protected $table = "coe_reasons";
}
