<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class ATDAccessType extends Model
{
    use HasFactory;

    protected $table = 'atd_access_types';

    protected $fillable = [
        "category",
        "role_id",
        "status",
        "roles",
    ];

    protected $casts = [
        "roles" => "array",
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, "role_id");
    }
}
