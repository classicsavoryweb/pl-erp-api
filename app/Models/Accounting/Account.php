<?php

namespace App\Models\Accounting;

use App\Models\ContributionAndTax;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;
    protected $connection="apsys";
    protected $table="tbl_account";

    public function contribution_and_tax() {
        return $this->belongsTo(ContributionAndTax::class);
    }
}
