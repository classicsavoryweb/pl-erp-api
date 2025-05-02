<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        "option","title","venue","speaker","status",
    ];

    protected $table = "training_titles";
}
