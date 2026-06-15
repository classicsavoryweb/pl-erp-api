<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RequestAndIssuance;
class RequestAndIssuanceItems extends Model
{
    use HasFactory;

    protected $table = 'request_and_issuance_items';

    protected $fillable = [
        'requested_number_id',
        'description',
        'size',
        'gender',
        'stock_on_hand',
        'requested_quantity',
        'remaining_quantity',
        'uom',
        'unit_price',
        'total_amount',
        'item_remarks',
        'uniform_item_no'
    ];

        public function issuance()
    {
        return $this->belongsTo(RequestAndIssuance::class, 'requested_number_id', 'request_number');
    }

   
}