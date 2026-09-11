<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformPhysicalCountCounter extends Model
{
    protected $fillable = [
        'physical_count_id',
        'employee_id',
        'employee_name',
    ];

    public function physicalCount()
    {
        return $this->belongsTo(UniformPhysicalCount::class, 'physical_count_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
