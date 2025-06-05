<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\SalaryPositionExport;
use App\Http\Controllers\Controller;
use App\Models\SalaryPosition;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Str;

class SalaryPositionController extends Controller
{
    public function getAllSalaryPositions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $salaryPositions = SalaryPosition::query();

            if ($request->has(["column_type", "key_word"])) {
               $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    $salaryPositions = $salaryPositions->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                        $query->where($relatedColumn, 'LIKE', "%$keyword%");
                    });
                } else {
                    $salaryPositions = $salaryPositions->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $salaryPositions = $salaryPositions->where("status", $request->status);
            }
            $salaryPositions = $salaryPositions->with("position");

            if ($request->has(["export"]) && $request->export) {
                $salaryPositions = $salaryPositions->get();
                return Excel::download(new SalaryPositionExport($salaryPositions), "salary_positions.xls");
            }

            $salaryPositions = $salaryPositions->paginate($perPage);

            return $this->sendResponse($salaryPositions);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAlLSalaryPositions(Request $request) {
        $request["export"] = true;

        return $this->getAllSalaryPositions($request);
    }

    public function getSalaryPositions(Request $request) {
        try {

            $salary_position = SalaryPosition::where("status", "active")->get();
            return $this->sendResponse(["salary_position" => $salary_position]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createSalaryPosition(Request $request) {
        try {
            $request->validate([
                "basic_salary" => "required",
                "position_id" => "required|unique:salary_positions,position_id",
                "rate_category" => "required",
                "status" => "required"
            ], [
                'position_id.unique' => 'The position has already been taken.'
            ]);
            SalaryPosition::create([
                "basic_salary" => $request->basic_salary,
                "position_id" => $request->position_id,
                "rate_category" => $request->rate_category,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Salary position created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editSalaryPosition(Request $request, $id) {
        try {
            $request->validate([
                "basic_salary" => "required",
                "position_id" => "required|unique:salary_positions,position_id,". $id,
                "rate_category" => "required",
                "status" => "required"
            ], [
                'position_id.unique' => 'The position has already been taken.'
            ]);
            SalaryPosition::where("id", $id)->update( [
                "basic_salary" => $request->basic_salary,
                "position_id" => $request->position_id,
                "rate_category" => $request->rate_category,
                "status" => $request->status
            ]);

            return $this->sendResponse(["message" => "Salary position updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
