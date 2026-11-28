<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffensesAttachment extends Model
{
      protected $table = 'offenses_attachment';

    protected $fillable = [
        'offenses_id',
        'attachment',
        'attachment_file_name',
    ];

    public function offense()
    {
        return $this->belongsTo(EmployeeOffense::class, 'offenses_id');
    }
}
