<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\ATDListExport;
use App\Http\Controllers\Controller;
use App\Exports\Compensation\EmployeeDeductionExport;
use App\Models\EmployeeDeduction;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Deduction;
use App\Models\Term;
use Carbon\Carbon;
use Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use DB;
use Str;
use Exception;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ATDController extends Controller
{
    public function getAllEmployeeDeductions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $deductions = EmployeeDeduction::where("source", "CSHUB");

            if ($request->has("employee_id") && $request->employee_id) {
                $deductions = $deductions->where('employee_id', $request->employee_id);
            }

            if ($request->has("branch_id") && $request->branch_id) {
                $deductions = $deductions->where('branch_id', $request->branch_id);
            }

            if ($request->has("deduction_id") && $request->deduction_id) {
                $deductions = $deductions->where('deduction_id', $request->deduction_id);
            }

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

            $deductions = $deductions->with(["employee", "branch", "deduction"]);

            if ($request->has(["export"]) && $request->export) {
                $deductions = $deductions->get();
                return Excel::download(new ATDListExport($deductions), "deductions.xls");
            }

            $deductions = $deductions->orderBy("start_date", "desc")
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

    public function getCSHubATD(Request $request) {
        try {
            $transactionLink = env('CS_HUB_URL_LINK')."/get-posted-atds";
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');
            $company = env('COMPANY_NAME');

            $payLoad = [
                "company" => $company,
            ];
            $branch = null;
            if ($request->filled(["branch_id"])) {
                $branch = Branch::find($request->branch_id);

                if ($branch) {
                    $payLoad["branch_number"] = $branch->branch_number;
                }
            }

            if ($request->filled(["transaction_number"])) {
                $payLoad["transaction_number"] = $request->transaction_number;
            }

            if ($request->filled(["date_from", "date_to"])) {
                $payLoad["date_from"] = $request->date_from;
                $payLoad["date_to"] = $request->date_to;
            }

            $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])->timeout(60)
            ->get($transactionLink, $payLoad);

            if ($response->successful()) {
                $data = $response->json();
                $transactions = [];

                foreach ($data['data']['data'] as $trans) {
                    $isExisting = EmployeeDeduction::where("transaction_number", $trans["transaction_number"])->first();

                    if ($isExisting) {
                        continue;
                    }

                    $transactions[] = $trans;
                }

                return $this->sendResponse(["transactions" => $transactions]);
            }

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createATD(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "transactions" => "required|array"
            ]);
            $groupedTransactions = [];
            foreach ($request->transactions as $transaction) {
                $employee = Employee::where("employee_number", $transaction["employee_number"])->first();
                $first_initial = strtoupper(substr($employee->first_name, 0, 1));
                $middle_initial = strtoupper(substr($employee->middle_name ?? '', 0, 1));
                $last_initial = strtoupper(substr($employee->last_name, 0, 1));
                $employee_number = $employee->employee_number;

                $base_reference = $first_initial . $middle_initial . $last_initial . $employee_number;

                $deduction_count = EmployeeDeduction::where("employee_id", $employee->id)
                    ->whereNotNull("deduction_ref")
                    ->where("deduction_ref", "!=", '')
                    ->count();

                $descriptions = strtolower($transaction["type"]);
                $deduction = Deduction::whereRaw(
                    'LOWER(description) = ?',
                    [$descriptions]
                )->first();

                $deductionId = str_pad($deduction->id, 8, '0', STR_PAD_LEFT);
                $deduction_reference = $base_reference . $deductionId . "|" . ($deduction_count + 1);
                $user = Auth::user();

                $dateStart = now()->format("Y-m-d");
                $dateEnd = Carbon::parse($dateStart)->addMonths($transaction["term"])->format("Y-m-d");

                $monthlyAmortization = $transaction["amount"];
                $terms = $transaction["term"];

                if ($transaction["term"] > 0) {
                    $monthlyAmortization = $transaction["amount"] / $terms;
                } else {
                    $term = Term::where("amount_from", "<=", $transaction["amount"])
                    ->where("amount_to", ">=", $transaction["amount"])
                    ->first();

                    $terms = $term->month;

                    if ($terms == 0) {
                        $monthlyAmortization = $transaction["amount"];
                    } else {
                        $monthlyAmortization = $transaction["amount"] / $terms;
                    }
                }

                $remarks = $transaction["transaction_number"]." | ".$transaction["transaction_date"]." | ".$transaction["type"]. " | ";

                foreach ($transaction["items"] as $idx => $item) {

                    if ($idx === 0) {
                        $remarks = $remarks . $item["item_name"];
                    } else {
                        $remarks = $remarks . " , " . $item["item_name"];
                    }
                }

                $atdBranch = Branch::where("branch_number", $transaction["branch_number"])->first();

                EmployeeDeduction::create([
                    "employee_id" => $employee->id,
                    "deduction_id" => $deduction->id,
                    "months" => $terms,
                    "amount" => $transaction["amount"],
                    "source_of_fund" => "HO",
                    "start_date" => $dateStart,
                    "end_date" => $dateEnd,
                    "monthly_amortization" => $monthlyAmortization,
                    "balance" => $transaction["amount"],
                    "remarks" => $remarks,
                    "transaction_number" => $transaction["transaction_number"],
                    "debit_id" => $deduction->debit_id,
                    "credit_id" => $deduction->credit_id,
                    "branch_id" => $atdBranch->id,
                    "block_payment" => "Yes",
                    "approved_status" => "Yes",
                    "deduction_ref" => $deduction_reference,
                    "created_by" => $user->proper_full_name,
                    "date_created" => date('Y-m-d'),
                    "end_loan_status" => "No",
                    "source" => "CSHUB"
                ]);

                $transactionNumber = $transaction["transaction_number"];

                $groupedTransactions[$transactionNumber][] = $transaction["employee_number"];
            }

            foreach ($groupedTransactions as $transactionNumber => $employeeNumbers) {
                $transactionLink = env('CS_HUB_URL_LINK')."/generate-atd/".$transactionNumber;
                $user = env('CS_HUB_ADMIN_EMAIL');
                $password = env('CS_HUB_ADMIN_PASSWORD');
                $company = env('COMPANY_NAME');
    
                $response = Http::withBasicAuth($user, $password)
                ->withHeaders([
                    "Accept" => "application/json",
                    "Content-Type" => "application/json"
                ])->timeout(60)
                ->post($transactionLink,[
                    "company" => $company,
                    "employee_numbers" => $employeeNumbers
                ]);

                if ($response->successful()) {

                    DB::commit();
                    return $this->sendResponse(["message" => "ATD successfully created"]);
                }
            }

            DB::rollBack();
            return $this->sendError(["message" => "Something went wrong"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
