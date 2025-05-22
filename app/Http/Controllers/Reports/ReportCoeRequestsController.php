<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\CoeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Exception;
use DB;
use Str;

class ReportCoeRequestsController extends Controller
{
    public function getAllCoeRequests(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $coeRequests = CoeRequest::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $coeRequests = $coeRequests->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $coeRequests = $coeRequests->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $coeRequests = $coeRequests->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["date_from", "date_to"])) {
                $coeRequests = $coeRequests->whereBetween('date_requested', [$request->date_from, $request->date_to]);
            }

            $coeRequests = $coeRequests->with(["employee"])
            ->orderBy("date_requested", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($coeRequests);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createCoeRequest(Request $request) {
        try {
            $request->validate([
                'employee_number' => "required",
                "include_salary" => "required",
                "coe_format" => "required",
                "coe_reason" => "required",
            ]);
            $employee = Employee::where("employee_number", $request->employee_number)->first();
            CoeRequest::create([
                ...$request->except(["employee_number"]),
                "employee_id" => $employee->id,
                "status" => "Pending",
                "date_requested" => now()->toDateString()
            ]);

            return $this->sendResponse(["message" => "COE Request created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editCoeRequest(Request $request, $id) {
        try {
            $request->validate([
                "include_salary" => "required",
                "coe_format" => "required",
                "coe_reason" => "required",
                "status" => "required",
            ]);

            CoeRequest::where("id", $id)
            ->update([
                ...$request->all()
            ]);

            return $this->sendResponse(["message" => "COE Request updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
