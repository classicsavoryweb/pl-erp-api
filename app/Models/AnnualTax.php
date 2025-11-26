<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnualTax extends Model
{
    use HasFactory;

    protected $table = "annual_tax";

    protected $fillabe = [
        "minimum_amount",
        "max_amount",
        "tax_lower_limit",
        "percentage",
    ];
}
