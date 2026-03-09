<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\IncentivesPerEmployeeExport;
use App\Http\Controllers\Controller;
use App\Models\IncentivesPerEmployee;
use App\Models\Employee;
use Illuminate\Http\Request;
use DB;
use Exception;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class IncentivesPerEmployeeController extends Controller
{
    public function getAllIncentivesPerEmployee(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $incentives_per_employee = IncentivesPerEmployee::with([
                "employee",
                "employee.current_position",
            ])->query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $incentives_per_employee = $incentives_per_employee->whereHas($relation, function ($query) use ($keyword) {
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
                        $incentives_per_employee = $incentives_per_employee->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $incentives_per_employee = $incentives_per_employee->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $incentives_per_employee = $incentives_per_employee->get();
                return Excel::download(new IncentivesPerEmployeeExport($incentives_per_employee), "incentives_per_employee.xls");
            }

            $incentives_per_employee = $incentives_per_employee->paginate($perPage);
            return $this->sendResponse($incentives_per_employee);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllIncentivesPerEmployee(Request $request) {
        $request["export"] = true;

        return $this->getAllIncentivesPerEmployee($request);
    }

    public function getIncentivesPerEmployee(Request $request) {
        try {
            $incentives_per_employee = IncentivesPerEmployee::all();
            return $this->sendResponse(["incentives_per_employee" => $incentives_per_employee]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createIncentivesPerEmployee(Request $request) {
        try {
            $request->validate([
                "employee_id" => "required",
                "amount" => "required",
                "max_amount" => "required",
            ]);

            $employee = Employee::where("id", $request->employee_id)->first();

            $isExisting = IncentivesPerEmployee::where("employee_id", $employee->id)->first();

            if ($isExisting) {
                return $this->sendError(["message" => "Employee is already existing."]);
            }

            $clean = [];
            foreach (['amount', 'max_amount'] as $field) {
                $clean[$field] = str_replace(',', '', $request->$field);
            }

            IncentivesPerEmployee::create([
                "employee_id" => $request->employee_id,
                "amount" => $clean['amount'],
                "max_amount" => $clean['max_amount'],
            ]);

            return $this->sendResponse(["message" => "Incentives created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editIncentivesPerEmployee(Request $request, $id) {
        try {
            $request->validate([
                'employee_id' => 'required|unique:incentives_per_employee,employee_id,' . $id,
            ], [
                'employee_id.unique' => 'Employee is already existing.',
            ]);

            $clean = [];
            foreach (['amount', 'max_amount'] as $field) {
                $clean[$field] = str_replace(',', '', $request->$field);
            }

            IncentivesPerEmployee::where("id", $id)->update([
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
