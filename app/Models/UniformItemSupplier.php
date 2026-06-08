<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniformItemSupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'uniform_item_id',
        'vendor_no',
        'vendor_name',
        'unit_price',
        'packaging_unit',
        'remarks',
    ];
}
