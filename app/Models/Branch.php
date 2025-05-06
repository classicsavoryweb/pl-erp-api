<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $table = "branches";
    protected $fillable = [
        "branch_number",
        "name",
        "short_name",
        "mall_admin",
        "address",
        "location",
        "date_open",
        "status"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}
