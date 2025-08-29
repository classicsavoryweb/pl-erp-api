<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Vendor;
use Illuminate\Http\Request;
use Exception;

class VendorController extends Controller
{

    public function getVendors(Request $request) {
        try {

            $vendors = Vendor::select('ven_no', 'ven_name')
            ->groupBy('ven_name', 'ven_no')
            ->orderBy('ven_name')
            ->get();

            return $this->sendResponse(["vendors" => $vendors]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
