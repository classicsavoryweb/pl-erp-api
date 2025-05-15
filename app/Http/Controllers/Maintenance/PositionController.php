<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Exception;

class PositionController extends Controller
{
    public function getAllPositions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $positions = Position::query();
            if ($request->has(["column_type", "key_word"])) {
               $positions = $positions->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $positions = $positions->where("status", $request->status);
            }
            $positions= $positions->with("station")->paginate($perPage);
            return $this->sendResponse($positions);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getPositions (Request $request) {
        try {
            $positions = Position::where("status", "active")->get();
            return $this->sendResponse(["positions" => $positions]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createPosition (Request $request) {
        try {
            $request->validate([
                "description" => "required",
                "rank" => "required",
                "status" => "required",
                "station_id" => "required"
            ]);

            Position::create([
                "description"=> $request->description,
                "rank" => $request->rank,
                "status" => $request->status,
                "station_id" => $request->station_id,
            ]);

            return $this->sendResponse(["message" => "Position saved successfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editPosition (Request $request, $id) {
        try {
            $request->validate([
                "description" => "required",
                "rank" => "required",
                "status" => "required",
                "station_id" => "required"
            ]);

            Position::where("id", $id)
            ->update([
                "description"=> $request->description,
                "rank" => $request->rank,
                "status" => $request->status,
                "station_id" => $request->station_id,
            ]);

            return $this->sendResponse(["message" => "Position saved successfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
