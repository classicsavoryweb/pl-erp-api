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
    public $timestamps = false;
    protected $primaryKey = 'ven_id';
    protected $fillable = [
        'vend_type_id',
        'ven_no',
        'ven_name',
        'ven_chk_name',
        'ven_address',
        'ven_tel_no',
        'ven_fax_no',
        'ven_tin_no',
        'term_id',
        'deb_acc_no',
        'cre_acc_no',
        'vat',
        'ewt_acc_no',
        'ven_stat',
        'ven_fields_input',
        'ven_particular',
        'related_id',
        'email',
        'pay_type',
    ];

    public function contribution_and_tax() {
        return $this->belongsTo(ContributionAndTax::class);
    }

    public function last_pay_setup() {
        return $this->belongsTo(LastPaySetup::class);
    }
}
