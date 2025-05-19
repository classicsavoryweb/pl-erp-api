<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\EmployeeOffense;
use Illuminate\Http\Request;
use Exception;
use Str;
use DB;

class ReportsOffensesController extends Controller
{
    public function getAllEmployeeOffenses(Request $request) {
        try {
            $perPage = $request->per_page ??10;
            $offenses = EmployeeOffense::query();
            
            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $offenses = $offenses->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $offenses = $offenses->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $offenses = $offenses->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["date_from", "date_to"])) {
                $offenses = $offenses->whereBetween('date_committed', [$request->date_from, $request->date_to]);
            }

            $offenses = $offenses->with([
                "employee",
                "sanction",
                "offense"
            ])->orderBy("date_committed","desc")
            ->paginate($perPage);

            return $this->sendResponse($offenses);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
