<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\PayrollListExport;
use App\Http\Controllers\Controller;
use App\Models\BankDetail;
use App\Models\Employee;
use App\Models\PayrollList;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Exception;
use DB;
use Str;

class PayrollListController extends Controller
{
    public function getAllPayrollList(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $payrollList = PayrollList::with([
                "employee",
                "employee.latest_bank_details",
                "employee.latest_bank_details.bank_account_type",
                "employee.current_position.salary",
                "employee.home_branch",
            ]);

            if ($request->has(["branch_id"])) {
                $payrollList = $payrollList->whereHas("employee", function ($query) use ($request) {
                    return $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $payrollList = $payrollList->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $payrollList = $payrollList->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $payrollList = $payrollList->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $payrollList = $payrollList->where("is_included", $request->status == "included" ? 1 : 0);
            }

            if ($request->has(["export"]) && $request->export) {
                $payrollList = $payrollList->get();
                return Excel::download(new PayrollListExport($payrollList), "payroll_list.xls");
            }

            $payrollList = $payrollList->paginate($perPage);

            return $this->sendResponse($payrollList);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportPayrollList(Request $request) {
        $request["export"] = true;

        return $this->getAllPayrollList($request);
    }

    public function getBankDetails(Request $request, $id) {
        try {

            $bankDetails = BankDetail::query()
            ->with("bank_account_type")
            ->where("employee_id", $id)
            ->first();

            return $this->sendResponse($bankDetails);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createPayrollList(Request $request) {
        try {
            $request->validate([
                "employee_id" => "required",
                "is_included" => "required",
                "is_statutory_only" => "required",
                "is_mancom" => "required",
                "is_on_maternity_leave" => "required",
                "is_bank" => "required",
                "is_hold" => "required"
            ]);

            $employee = Employee::where("id", $request->employee_id)->first();

            $isExisting = PayrollList::where("employee_id", $employee->id)->first();

            if ($isExisting) {
                return $this->sendError(["message" => "Employee is already existing."]);
            }

            PayrollList::create([
                "employee_id" => $request->employee_id,
                "is_included" => $request->is_included,
                "is_statutory_only" => $request->is_statutory_only,
                "is_mancom" => $request->is_mancom,
                "is_on_maternity_leave" => $request->is_on_maternity_leave,
                "is_bank" => $request->is_bank,
                "is_hold" => $request->is_hold,
                "holded_at" => $request->is_hold ? now() : null,
                "remarks" => $request->remarks,
            ]);

            return $this->sendResponse(["message" => "Payroll created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editPayrollList(Request $request, $id) {
        try {
            $request->validate([
                "employee_id" => 'required',
                "is_included" => "required",
                "is_statutory_only" => "required",
                "is_mancom" => "required",
                "is_on_maternity_leave" => "required",
                "is_bank" => "required",
                "is_hold" => "required"
            ]);

            PayrollList::where("id", $id)->update([
                "is_included" => $request->is_included,
                "is_statutory_only" => $request->is_statutory_only,
                "is_mancom" => $request->is_mancom,
                "is_on_maternity_leave" => $request->is_on_maternity_leave,
                "is_bank" => $request->is_bank,
                "is_hold" => $request->is_hold,
                "holded_at" => $request->is_hold ? now() : null,
                "remarks" => $request->remarks
            ]);

            return $this->sendResponse(["message" => "Payroll updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
