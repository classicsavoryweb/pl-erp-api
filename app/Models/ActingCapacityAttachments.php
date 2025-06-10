<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActingCapacityAttachments extends Model
{
    use HasFactory;

    protected $table = "acting_capacity_attachments";

    protected $fillable = [
        "acting_capacity_id",
        "file_path",
        "file_name",
    ];
    // \\192.168.2.5\Transfer Folder\

    public function acting_capacity() {
        return $this->belongsTo(ActingCapacity::class);
    }
}
