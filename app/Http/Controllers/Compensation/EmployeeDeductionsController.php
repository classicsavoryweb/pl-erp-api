<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\EmployeeDeductionExport;
use App\Http\Controllers\Controller;
use App\Models\EmployeeDeduction;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Deduction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use DB;
use Str;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeDeductionsController extends Controller
{
    public function getAllEmployeeDeductions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $deductions = EmployeeDeduction::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $deductions = $deductions->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $deductions = $deductions->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $deductions = $deductions->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $deductions = $deductions->get();
                return Excel::download(new EmployeeDeductionExport($deductions), "deductions.xls");
            }

            $deductions = $deductions
            ->with(["employee", "branch", "deduction"])
            ->paginate($perPage);

            return $this->sendResponse($deductions);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllEmployeeDeductions(Request $request) {
        $request["export"] = true;

        return $this->getAllEmployeeDeductions($request);
    }

    public function getEmployeeDeduction(Request $request) {
        try {
            $deductions = EmployeeDeduction::where("status", "active")->get();
            return $this->sendResponse(["deductions" => $deductions]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createEmployeeDeduction(Request $request)
    {
        try {
            $request->validate([
                "employee_id" => "required",
                "deduction_id" => "required",
                "months" => "required",
                "amount" => "required",
                "start_date" => "required",
                "end_date" => "required",
                "monthly_amortization" => "required",
                "balance" => "required",
                "branch_id" => "required",
                "block_payment" => "required",
            ]);

            $employee = Employee::where("id", $request->employee_id)->first();

            $first_initial = strtoupper(substr($employee->first_name, 0, 1));
            $middle_initial = strtoupper(substr($employee->middle_name ?? '', 0, 1));
            $last_initial = strtoupper(substr($employee->last_name, 0, 1));
            $employee_number = $employee->employee_number;

            $base_reference = $first_initial . $middle_initial . $last_initial . $employee_number;

            $deduction_count = EmployeeDeduction::where("employee_id", $request->employee_id)
                ->whereNotNull("deduction_ref")
                ->where("deduction_ref", "!=", '')
                ->count();

            $deduction_reference = $base_reference . $request->deduction_id . "|" . ($deduction_count + 1);

            $branch = Branch::where("name", "Head Office")->first();
            $user = Auth::user();
            $employee = $user->employee;

            $deduction_id = $request->deduction_id;

            $last_reference = EmployeeDeduction::where("deduction_id", $deduction_id)
                ->orderByDesc("reference_doc")
                ->value("reference_doc");

            $deduction = Deduction::where("id", $deduction_id)->first();

            if (!$last_reference) {
                $reference_prefix = str_pad($deduction_id, 3, "0", STR_PAD_LEFT);
                $reference_docs = $reference_prefix . "000001";
            } else {
                $numeric_reference = (int)$last_reference + 1;
                $reference_docs = str_pad($numeric_reference, 9, "0", STR_PAD_LEFT);
            }

            while (EmployeeDeduction::where("reference_doc", $reference_docs)->exists()) {
                $numeric_reference++;
                $reference_docs = str_pad($numeric_reference, 9, "0", STR_PAD_LEFT);
            }

            EmployeeDeduction::create([
                "employee_id" => $request->employee_id,
                "deduction_id" => $request->deduction_id,
                "reference_doc" => $reference_docs,
                "months" => $request->months,
                "amount" => $request->amount,
                "source_of_fund" => $branch->short_name,
                "start_date" => $request->start_date,
                "end_date" => $request->end_date,
                "monthly_amortization" => $request->monthly_amortization,
                "balance" => $request->balance,
                "remarks" => $request->remarks,
                "debit_id" => $deduction->debit_id,
                "credit_id" => $deduction->credit_id,
                "branch_id" => $request->branch_id,
                "block_payment" => $request->block_payment,
                "approved_by" => $request->approved_by,
                "approved_date" => $request->approved_date,
                "approved_status" => $request->approved_status,
                "deduction_ref" => $deduction_reference,
                "deduction_status" => $request->deduction_status,
                "created_by" => $employee->properFullName,
                "date_created" => date('Y-m-d'),
                "end_loan_reason" => $request->end_loan_reason,
                "end_loan_status" => $request->end_loan_status,
            ]);

            return $this->sendResponse(["message" => "Deduction created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editEmployeeDeduction(Request $request, $id) {
        try {
            $request->validate([
                "employee_id" => "required",
                "deduction_id" => "required",
                "months" => "required",
                "amount" => "required",
                "start_date" => "required",
                "end_date" => "required",
                "monthly_amortization" => "required",
                "balance" => "required",
                "branch_id" => "required",
                "block_payment" => "required",
            ]);

            EmployeeDeduction::where("id", $id)->update([
                "employee_id" => $request->employee_id,
                "deduction_id" => $request->deduction_id,
                "months" => $request->months,
                "amount" => $request->amount,
                "start_date" => $request->start_date,
                "end_date" => $request->end_date,
                "monthly_amortization" => $request->monthly_amortization,
                "balance" => $request->balance,
                "branch_id" => $request->branch_id,
                "block_payment" => $request->block_payment,
            ]);

            return $this->sendResponse(["message" => "Deduction updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
