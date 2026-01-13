<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use App\Models\Deduction;

class ATDAccessType extends Model
{
    use HasFactory;

    protected $table = 'atd_access_types';

    protected $fillable = [
        "role_id",
        "status",
        "deduction_id",
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, "role_id");
    }

    public function deduction()
    {
        return $this->belongsTo(Deduction::class, "deduction_id");
    }
}
