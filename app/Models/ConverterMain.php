<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverterMain extends Model
{
    use HasFactory;

    protected $table="converter_main";
    protected $fillable = [
        "batch_number",
        "total_amount",
        "count",
        "status",
        "remarks",
        "added_by",
        "posted_by",
        "generated_by",
        "voided_by",
        "posted_at",
        "generated_at",
        "voided_at",
        "bank_account_type_id",
        "transaction_type_id",
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            $model->update([
                'batch_number' => 'CON-' . str_pad($model->id, 7, '0', STR_PAD_LEFT)
            ]);
        });
    }

    public function added_by() {
        return $this->hasOne(User::class, "id", "added_by");
    }

    public function posted_by() {
        return $this->hasOne(User::class, "id", "posted_by");
    }

    public function generated_by() {
        return $this->hasOne(User::class, "id", "generated_by");
    }

    public function voided_by() {
        return $this->hasOne(User::class, "id", "voided_by");
    }

    public function converter_details() {
        return $this->hasMany(ConverterDetail::class, "converter_main_id", "id");
    }
}
