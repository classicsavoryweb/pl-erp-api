<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeOffense extends Model
{
    use HasFactory;
    protected $fillable = [
        'offense_id',
        'employee_id',
        'sanction_id',
        'transgression',
        'repetition',
        'summary',
        'date_committed',
        'offense_action',
        'date_served_ao',
        'suspension_dates',
        'date_served_da',
        'remarks',
    ];

    protected $table = "employee_offenses";

    public function offense(){
        return $this->hasOne(Offense::class,"id","offense_id");
    }

    public function sanction(){
        return $this->hasOne(Sanction::class,"id","sanction_id");
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}
