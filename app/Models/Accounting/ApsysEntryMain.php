<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApsysEntryMain extends Model
{
    use HasFactory;

    protected $connection = "apsys";
    protected $table = "tbl_entry_main";
    protected $guarded = ['id'];
    protected $primaryKey = "ent_tran_no_id";
    public $timestamps = false;

    protected static function booted(): void
    {
        static::created(function (ApsysEntryMain $entry) {
            $entry->ent_tran_no = 'PSD' . $entry->ent_tran_no_id . 'ERP';
            $entry->saveQuietly();
        });
    }
}
