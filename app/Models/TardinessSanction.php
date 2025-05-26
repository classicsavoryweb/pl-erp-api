<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TardinessSanction extends Model
{
    use HasFactory;

    protected $table = "tardiness_sanctions";
    protected $fillable = [
        "count",
        "punishment",
        "rank",
        "status"
    ];

    public function tardiness () {
        return $this->belongsTo(Tardiness::class);
    }
}
