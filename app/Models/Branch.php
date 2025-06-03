<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $table = "branches";
    protected $fillable = [
        "name",
        "branch_number",
        "status",
        "short_name",
        "address",
        "location",
        "date_open",
        "company",
        "branch_email",
        "rate_code",
        'branch_head_employee_number',
        "group_head_employee_number",
        'branch_head',
        "group_head",
        'kitchen_oic_employee_number',
        'kitchen_oic',
        'mancom_employee_number',
        'mancom',
    ];

    public function employee() {
        return $this->belongsToMany(Employee::class);
    }
}
