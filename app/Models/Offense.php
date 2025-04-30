<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offense extends Model
{
    use HasFactory;

    protected $table = "offenses";
    protected $fillable = [
        "category",
        "subcategory",
        "description",
        "status"
    ];
}
