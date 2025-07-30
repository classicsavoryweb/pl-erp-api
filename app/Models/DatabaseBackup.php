<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseBackup extends Model
{
    use HasFactory;

    protected $table = "database_backup";
    protected $fillable = [
        "date",
        "file_name",
        "file_path",
        "backed_up_by",
    ];
}
