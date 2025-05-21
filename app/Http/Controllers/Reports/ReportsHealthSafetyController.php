<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\EmployeeHealthSafety;
use Illuminate\Http\Request;
use Exception;
use DB;
use Str;

class ReportsHealthSafetyController extends Controller
{
    public function getAllReportsHealthAndSafety(Request $request) {
        try {
            $perPage = $request->per_page ??10;

            $healthSafety = EmployeeHealthSafety::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $healthSafety = $healthSafety->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $healthSafety = $healthSafety->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $healthSafety = $healthSafety->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["date_from", "date_to"])) {
                $healthSafety = $healthSafety->whereBetween('consultation_date', [$request->date_from, $request->date_to]);
            }

            $healthSafety = $healthSafety->with([
                "employee",
                "diagnosis"
            ])
            ->orderBy("consultation_date","DESC")
            ->paginate($perPage);

            return $this->sendResponse($healthSafety);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
