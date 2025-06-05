<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\StationExport;
use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class StationController extends Controller
{
    public function getAllStation(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $stations = Station::query();
            if ($request->has(["column_type", "key_word"])) {
               $stations = $stations->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $stations = $stations->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $stations = $stations->get();
                return Excel::download(new StationExport($stations), "stations.xls");
            }

            $stations = $stations->paginate($perPage);
            return $this->sendResponse($stations);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllStation(Request $request) {
        $request["export"] = true;

        return $this->getAllStation($request);
    }

    public function getStations(Request $request) {
        try {

            $stations = Station::where("status", "active")->get();
            return $this->sendResponse(["stations" => $stations]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function deleteStation(Request $request, $id) {
        try {
            $station = Station::find($id);
            $station->delete();
            return $this->sendResponse(["message" => "Station deleted successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createStation(Request $request) {
        try {
            $request->validate([
                "name" => "required|unique:stations",
                "station_order" => "required",
                "status" => "required"
            ]);
            Station::create([
                "name" => $request->name,
                "station_order" => $request->station_order,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Station created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editStation (Request $request, $id) {
        try {
            $request->validate([
                "name" => "required|unique:stations,name,".$id,
                "station_order" => "required",
                "status" => "required"
            ]);
            $station = Station::find($id);
            $station->update([
                "name" => $request->name,
                "station_order" => $request->station_order,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Station updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
