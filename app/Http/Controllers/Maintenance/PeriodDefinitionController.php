<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\PeriodDefinitionExport;
use App\Http\Controllers\Controller;
use App\Models\PeriodDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class PeriodDefinitionController extends Controller
{
    public function getAllPeriodDefinition(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $period_definition = PeriodDefinition::query();
            if ($request->has(["column_type", "key_word"])) {
                $period_definition = $period_definition->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $period_definition = $period_definition->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $period_definition = $period_definition->get();
                return Excel::download(new PeriodDefinitionExport($period_definition), "period_definition.xls");
            }

            $period_definition = $period_definition->paginate($perPage);
            return $this->sendResponse($period_definition);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllPeriodDefinition(Request $request) {
        $request["export"] = true;

        return $this->getAllPeriodDefinition($request);
    }

    public function getPeriodDefinition(Request $request) {
        try {
            $period_definition = PeriodDefinition::where("status", "active")->get();
            return $this->sendResponse(["period_definition" => $period_definition]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createPeriodDefinition(Request $request)
    {
        try {
            $request->validate([
                'months' => 'required|array|min:1',
                'months.*' => 'required|string',
                'year' => 'required|numeric',
                'status' => 'required|string',
            ]);

            $existingPeriod = PeriodDefinition::where('year', $request->year)->first();
            
            if ($existingPeriod) {
                return $this->sendError(["message" => "Year is already existing."]);
            }

            foreach ($request->months as $month) {
                PeriodDefinition::create([
                    'month' => $month,
                    'year' => $request->year,
                    'status' => $request->status,
                ]);
            }

            return $this->sendResponse(["message" => "Period definition created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editPeriodDefinition(Request $request, $id) {
        try {
            $request->validate([
                "status" => "required",
            ]);

            PeriodDefinition::where("id", $id)->update([
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Period definition updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
