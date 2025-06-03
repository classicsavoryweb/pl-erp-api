<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeTrainings extends Pivot
{
    use HasFactory;

    public $incrementing = true;
    protected $fillable = [
        "employee_id",
        "training_title",
        "speaker",
        "venue",
        "batch",
        "others",
        "date",
        "covered_date",
        "option",
        "score",
    ];
    protected $table = "employee_trainings";

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function training()
    {
        return $this->belongsTo(TrainingTitle::class, "training_title_id", "id");
    }
}
