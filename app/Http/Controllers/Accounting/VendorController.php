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
            $vendors = Vendor::select('ven_no', 'ven_name');

            if ($request->has("keyWord")) {
                $keyword = $request->keyWord;
                $vendors = $vendors->where("ven_name", "LIKE", "%$keyword%");
            }

            if ($request->has("status") && in_array($request->status, ['active', 'inactive'])) {
                $vendors = $vendors->where('ven_stat', ucfirst($request->status));
            }

            $vendors = $vendors
                ->groupBy('ven_name', 'ven_no')
                ->orderBy('ven_name')
                ->get();

            return $this->sendResponse(["vendors" => $vendors]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
