<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RequirementsAttachment extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "requirements_attachments";
    protected $fillable = [
        "employee_id",
        "file_name",
        "file_path"
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Requirement Attachment')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
