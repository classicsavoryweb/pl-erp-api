<?php

namespace App\Http\Controllers\Uniform;

use App\Models\RequestAndIssuanceItems;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

use App\Models\UniformItem;


class RequestAndIssuanceItemsController extends Controller
{
    

    public function getOptionsMasterList()
    {
        try {
            return $this->sendResponse([
                'items' => UniformItem::with('gender', 'size')
                    ->where('status', 'active')
                    ->orderBy('description')
                    ->get(),
            ]);
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

   
}
