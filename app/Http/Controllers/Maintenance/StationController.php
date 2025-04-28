<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\Request;
use Exception;

class StationController extends Controller
{
    public function getAllStation(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $stations = Station::paginate($perPage);
            return $this->sendResponse($stations);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }
}
