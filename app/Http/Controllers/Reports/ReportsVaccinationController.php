<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Exports\AllRecords\EmployeeVaccinationExport;
use App\Models\EmployeeVaccine;
use Illuminate\Http\Request;
use Exception;
use DB;
use Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportsVaccinationController extends Controller
{
    public function getAllReportsVaccines(Request $request) {
        try {
            $perPage = $request->per_page ??10;

            $vaccines = EmployeeVaccine::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $vaccines = $vaccines->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $vaccines = $vaccines->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $vaccines = $vaccines->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $vaccines = $vaccines->where("status", $request->status);
            }

            if ($request->has(["date_from", "date_to"])) {
                $vaccines = $vaccines->whereBetween('date', [$request->date_from, $request->date_to]);
            }

            $vaccines = $vaccines->with([
                "vaccine",
                "employee"
            ])
            ->orderBy("date","DESC");

            if ($request->has(["export"]) && $request->export) {
                $vaccines = $vaccines->get();
                return Excel::download(new EmployeeVaccinationExport($vaccines), "vaccination.xls");
            }

            $vaccines = $vaccines->paginate($perPage);

            return $this->sendResponse($vaccines);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllReportsVaccines(Request $request)
    {
        $request["export"] = true;

        return $this->getAllReportsVaccines($request);
    }
}
