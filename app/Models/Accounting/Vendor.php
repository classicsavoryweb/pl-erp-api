<?php

namespace App\Models\Accounting;

use App\Models\ContributionAndTax;
use App\Models\LastPaySetup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $connection="apsys";
    protected $table="tbl_vendor";

    public function contribution_and_tax() {
        return $this->belongsTo(ContributionAndTax::class);
    }

    public function last_pay_setup() {
        return $this->belongsTo(LastPaySetup::class);
    }
}
