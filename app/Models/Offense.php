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
        "offense_number",
        "subcategory",
        "description",
        "status"
    ];

    public function employee_offense() {
        return $this->belongsTo(EmployeeOffense::class);
    }

}
