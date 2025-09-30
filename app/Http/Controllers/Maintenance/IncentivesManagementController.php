<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\IncentivesManagementExport;
use App\Http\Controllers\Controller;
use App\Models\IncentivesManagement;
use App\Models\Employee;
use Illuminate\Http\Request;
use DB;
use Exception;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class IncentivesManagementController extends Controller
{
    public function getAllIncentivesManagement(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $incentives_management = IncentivesManagement::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $incentives_management = $incentives_management->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            })
                            ->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $incentives_management = $incentives_management->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $incentives_management = $incentives_management->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $incentives_management = $incentives_management->get();
                return Excel::download(new IncentivesManagementExport($incentives_management), "incentives_management.xls");
            }

            $incentives_management = $incentives_management->with([
                "employee",
                "employee.current_position",
            ])->paginate($perPage);
            return $this->sendResponse($incentives_management);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllIncentivesManagement(Request $request) {
        $request["export"] = true;

        return $this->getAllIncentivesManagement($request);
    }

    public function getIncentivesManagement(Request $request) {
        try {
            $incentives_management = IncentivesManagement::all();
            return $this->sendResponse(["incentives_management" => $incentives_management]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createIncentivesManagement(Request $request) {
        try {
            $request->validate([
                "employee_id" => "required",
                "amount" => "required",
                "max_amount" => "required",
            ]);

            $employee = Employee::where("id", $request->employee_id)->first();

            $isExisting = IncentivesManagement::where("employee_id", $employee->id)->first();

            if ($isExisting) {
                return $this->sendError(["message" => "Employee is already existing."]);
            }

            $clean = [];
            foreach (['amount', 'max_amount'] as $field) {
                $clean[$field] = str_replace(',', '', $request->$field);
            }

            IncentivesManagement::create([
                "employee_id" => $request->employee_id,
                "amount" => $clean['amount'],
                "max_amount" => $clean['max_amount'],
            ]);

            return $this->sendResponse(["message" => "Incentives created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editIncentivesManagement(Request $request, $id) {
        try {
            $request->validate([
                'employee_id' => 'required|unique:incentives_management,employee_id,' . $id,
            ], [
                'employee_id.unique' => 'Employee is already existing.',
            ]);

            $clean = [];
            foreach (['amount', 'max_amount'] as $field) {
                $clean[$field] = str_replace(',', '', $request->$field);
            }

            IncentivesManagement::where("id", $id)->update([
                "employee_id" => $request->employee_id,
                "amount" => $clean['amount'],
                "max_amount" => $clean['max_amount'],
            ]);

            return $this->sendResponse(["message" => "Incentives updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
