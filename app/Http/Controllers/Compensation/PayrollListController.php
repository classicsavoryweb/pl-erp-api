<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\PayrollListExport;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Vendor;
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
                "employee.current_position",
                "employee.current_position.salary",
                "employee.home_branch",
                "employee.current_branch",
                "employee.latest_position",
                "employee.schedule",
                "employee.schedule.schedule_type",
                "current_bank_details",
                "current_bank_details.bank_account_type"
            ]);

        //             $salaryPosition = $this->current_position->salary;
        // $rateCode = $this->home_branch->rate_code;

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

            if ($request->has(["employee_status"]) && in_array($request->employee_status, ["active", "inactive"])) {
                $payrollList = $payrollList->whereHas("employee", function ($query) use ($request) {
                    $query->where("status", $request->employee_status);
                });
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
            DB::beginTransaction();

            $request->validate([
                "employee_id" => "required",
                "is_included" => "required",
                "is_statutory_only" => "required",
                "is_mancom" => "required",
                "is_on_maternity_leave" => "required",
                "is_bank" => "required",
                "is_hold" => "required"
            ]);

            $employee = Employee::with(['latest_bank_details'])->where("id", $request->employee_id)->first();

            $isExisting = PayrollList::where("employee_id", $employee->id)->first();

            if ($isExisting) {
                return $this->sendError(["message" => "Employee is already existing."]);
            }

            $bank_details_id = null;

            if ($request->is_bank == 1) {
                if (!$employee->latest_bank_details) {
                    return $this->sendError(["message" => "Bank details is missing"]);
                }

                $bank_details_id = $employee->latest_bank_details->id;
            }

            PayrollList::create([
                "employee_id" => $request->employee_id,
                "salary_amount" => round((float) $employee?->monthlySalary(), 2),
                "is_included" => $request->is_included,
                "is_statutory_only" => $request->is_statutory_only,
                "is_mancom" => $request->is_mancom,
                "is_on_maternity_leave" => $request->is_on_maternity_leave,
                "has_complete_documents" => $request->has_complete_documents ?? 0,
                "is_bank" => $request->is_bank,
                "is_hold" => $request->is_hold,
                "bank_details_id" => $bank_details_id,
                "holded_at" => $request->is_hold ? now() : null,
                "remarks" => $request->remarks,
            ]);

            Vendor::updateOrCreate([
                "ven_no" => $employee->employee_number,
                "ven_name"=> trim($employee->full_name),
            ], [
                "vend_type_id" => 1,
                "ven_chk_name"=> trim($employee->full_name),
                "ven_address" => $employee->address,
                "ven_tel_no" => $employee->contact_number,
                "ven_fax_no" => "",
                "ven_tin_no" => "",
                "term_id" => "00008",
                "deb_acc_no" => "None",
                "cre_acc_no" => "4000000",
                "vat" => "N/A",
                "ewt_acc_no" => "EWT005",
                "ven_stat" => "Active",
                "ven_fields_input" => "OPTIONAL",
                "ven_particular" => "Employee Vendor Account",
                "related_id" => $employee->employee_number,
                "email" => $employee->email,
                "pay_type" => "Check",
            ]);
            
            DB::commit();
            return $this->sendResponse(["message" => "Employee added succesfully"]);
        } catch(Exception $e) {
            DB::rollBack();
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
                "is_hold" => "required",
                "salary_amount" => "nullable|numeric|min:0"
            ]);

            $payrollList = PayrollList::find($id);
            if (!$payrollList) {
                return  $this->sendError(["message" => "Record not found"]);
            }

            $payrollList->update([
                "is_included" => $request->is_included,
                "is_statutory_only" => $request->is_statutory_only,
                "is_mancom" => $request->is_mancom,
                "is_on_maternity_leave" => $request->is_on_maternity_leave,
                "has_complete_documents" => $request->has_complete_documents ?? 0,
                "is_bank" => $request->is_bank,
                "is_hold" => $request->is_hold,
                "holded_at" => $request->is_hold ? now() : null,
                "remarks" => $request->remarks,
                "bank_details_id" => $request->bank_details_id ?? null,
                "salary_amount" => $request->salary_amount ?? 0
            ]);

            return $this->sendResponse(["message" => "Employee updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
