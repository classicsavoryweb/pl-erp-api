<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\IncentivesManagementExport;
use App\Exports\Maintenance\IncentivesPositionExport;
use App\Http\Controllers\Controller;
use App\Models\IncentivesManagement;
use App\Models\Employee;
use App\Models\IncentivePosition;
use App\Models\Position;
use Illuminate\Http\Request;
use DB;
use Exception;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class IncentivesPositionController extends Controller
{
    public function getAllIncentivesPosition(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $incentivePositions = IncentivePosition::with([
                "position"
            ]);

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);
                    $incentivePositions = $incentivePositions->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                        $query->where($relatedColumn, 'LIKE', "%$keyword%");
                    });

                } else {
                    $incentivePositions = $incentivePositions->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $incentivePositions = $incentivePositions->get();
                return Excel::download(new IncentivesPositionExport($incentivePositions), "incentivePositions.xls");
            }

            $incentivePositions = $incentivePositions->paginate($perPage);
            return $this->sendResponse($incentivePositions);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllIncentivesPosition(Request $request) {
        $request["export"] = true;

        return $this->getAllIncentivesPosition($request);
    }

    public function createIncentivesPosition(Request $request) {
        try {
            $request->validate([
                "position_id" => "required",
                "amount" => "required",
                "oic_additional_amount" => "required",
            ]);

            $position = Position::find($request->position_id);

            $isExisting = IncentivePosition::where("position_id", $position->id)->first();

            if ($isExisting) {
                return $this->sendError(["message" => "Position is already existing."]);
            }

            IncentivePosition::create([
                "position_id" => $request->position_id,
                "amount" => $request->amount,
                "oic_additional_amount" => $request->oic_additional_amount,
            ]);

            return $this->sendResponse(["message" => "Incentives created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editIncentivesPosition(Request $request, $id) {
        try {
            $request->validate([
                'position_id' => 'required|unique:incentives_position,position_id,' . $id,
            ], [
                'position_id.unique' => 'Employee is already existing.',
            ]);

            IncentivePosition::where("id", $id)->update([
                "position_id" => $request->position_id,
                "amount" => $request->amount,
                "oic_additional_amount" => $request->oic_additional_amount,
            ]);

            return $this->sendResponse(["message" => "Incentives updated succesfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
