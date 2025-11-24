<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApsysEntry extends Model
{
    use HasFactory;

    protected $connection = "apsys";
    protected $table = "tbl_entry";
    protected $guarded = ['id'];
    public $timestamps = false;
}
