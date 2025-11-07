<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverterDetail extends Model
{
    use HasFactory;

    protected $table="converter_details";
    protected $fillable = [
        "converter_main_id",
        "employee_id",
        "branch_id",
        "transaction_type_id",
        "terms",
        "amount",
        "source",
        "status",
        "remarks",
        "added_by",
        "transaction_number"
    ];

    public function converter_main() {
        return $this->belongsTo(ConverterMain::class);
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function transaction_type() {
        return $this->hasOne(CSHubTransactionType::class, "id", "transaction_type_id");
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }
}
