<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\ATDListExport;
use App\Http\Controllers\Controller;
use App\Exports\Compensation\EmployeeDeductionExport;
use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\EmployeeDeduction;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Deduction;
use App\Models\ATDAccessType;
use App\Models\Entry;
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
    private function getUserATDAllowedDeductionIds(): array
    {
        $user = Auth::user();
        $roleIds = $user?->roles?->pluck('id')->map(fn ($id) => (int) $id)->values()->all() ?? [];

        if (empty($roleIds)) {
            return [];
        }

        $query = ATDAccessType::query()->where("status", "active");
        $query->whereIn("role_id", $roleIds);

        return $query->pluck("deduction_id")->filter()->unique()->values()->all();
    }

    private function applyATDAccessFilter($deductionsQuery)
    {
        $allowedDeductionIds = $this->getUserATDAllowedDeductionIds();

        if (empty($allowedDeductionIds)) {
            return $deductionsQuery->whereRaw("1 = 0");
        }

        return $deductionsQuery->whereIn("deduction_id", $allowedDeductionIds);
    }

    private function generateEntryId(&$counterEntry)
    {
        if ($counterEntry === null) {
            $lastEntry = Entry::orderBy('entry_id', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->entry_id, 3);
                $counterEntry = $lastEntryIdNumber + 1;
            } else {
                $counterEntry = 1; 
            }
        }

        return 'PSD' . str_pad($counterEntry++, 8, '0', STR_PAD_LEFT);
    }

    private function generateApsysEntryId(&$counterApsysEntry)
    {
        if ($counterApsysEntry === null) {
            $lastEntry = ApsysEntry::orderBy('ent_tran_no', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->ent_tran_no, 3);
                $counterApsysEntry = $lastEntryIdNumber + 1;
            } else {
                $counterApsysEntry = 1; 
            }
        }

        return 'PSD' . str_pad($counterApsysEntry++, 8, '0', STR_PAD_LEFT);
    }

    private function generateApsysEntryMainId(&$counterApsysEntryMain)
    {
        if ($counterApsysEntryMain === null) {
            $lastEntry = ApsysEntryMain::orderBy('ent_tran_no', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->ent_tran_no, 3);
                $counterApsysEntryMain = $lastEntryIdNumber + 1;
            } else {
                $counterApsysEntryMain = 1; 
            }
        }

        return 'PSD' . str_pad($counterApsysEntryMain++, 8, '0', STR_PAD_LEFT);
    }

    public function getAllEmployeeDeductions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $deductions = EmployeeDeduction::where("source", "CSHUB");
            $deductions = $this->applyATDAccessFilter($deductions);

            if ($request->has("employee_id") && $request->employee_id) {
                $deductions = $deductions->where('employee_id', $request->employee_id);
            }

            if ($request->has("branch_id") && $request->branch_id) {
                $deductions = $deductions->whereHas("employee", function ($query) use ($request) {
                    $query->whereHas("current_branch", function ($q) use ($request) {
                        $q->where('branch_id', $request->branch_id);
                    });
                });
            }

            if ($request->has("deduction_id") && $request->deduction_id) {
                $deductions = $deductions->where('deduction_id', $request->deduction_id);
            }

            if ($request->filled("created_at_from") && $request->filled("created_at_to")) {
                $startDate = Carbon::parse($request->created_at_from)->startOfDay();
                $endDate = Carbon::parse($request->created_at_to)->endOfDay();
                $deductions = $deductions->whereBetween('created_at', [$startDate, $endDate]);
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

            $deductions = $deductions->with(["employee", "employee.current_branch", "branch", "deduction"]);

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

    public function getAllowedATDDeductions(Request $request)
    {
        try {
            $allowedDeductionIds = $this->getUserATDAllowedDeductionIds();
            if (empty($allowedDeductionIds)) {
                return $this->sendResponse(["deductions" => []]);
            }

            $deductions = Deduction::whereIn("id", $allowedDeductionIds)
                ->where("status", "active")
                ->orderBy("description")
                ->get();

            return $this->sendResponse(["deductions" => $deductions]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getCSHubATD(Request $request) {
        try {
            $allowedDeductionIds = $this->getUserATDAllowedDeductionIds();
            if (empty($allowedDeductionIds)) {
                return $this->sendResponse(["transactions" => []]);
            }

            $allowedDeductions = Deduction::whereIn("id", $allowedDeductionIds)
                ->where("status", "active")
                ->get(["id", "description"]);
            $allowedDescriptions = $allowedDeductions
                ->pluck("description")
                ->map(fn ($desc) => strtolower(trim($desc)))
                ->unique()
                ->values()
                ->all();
            $allowedDescriptionsMap = array_flip($allowedDescriptions);

            if ($request->filled("deduction_id")) {
                $deductionId = (int) $request->deduction_id;
                $selectedDeduction = $allowedDeductions->firstWhere("id", $deductionId);
                if (!$selectedDeduction) {
                    return $this->sendError(["message" => "You are not allowed to access this deduction type."]);
                }
                $selectedDescription = strtolower(trim($selectedDeduction->description));
                $allowedDescriptionsMap = [$selectedDescription => true];
            }

            if ($request->filled("deduction_type")) {
                $deductionType = strtolower(trim($request->deduction_type));
                if ($deductionType === "" || !isset($allowedDescriptionsMap[$deductionType])) {
                    return $this->sendError(["message" => "You are not allowed to access this deduction type."]);
                }
                $allowedDescriptionsMap = [$deductionType => true];
            }

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
                    $transType = strtolower(trim($trans["type"] ?? ""));
                    if ($transType === "" || !isset($allowedDescriptionsMap[$transType])) {
                        continue;
                    }

                    $employee = Employee::where("employee_number", $trans["employee_number"])->first();

                    if (!$employee) {
                        continue;
                    }

                    $isExisting = EmployeeDeduction::where("employee_id", $employee->id)
                    ->where(function ($query) use ($trans) {
                        $query->where("transaction_number", $trans["transaction_number"]);
                    })
                    ->first();

                    if ($isExisting) {
                        continue;
                    }

                    $transactions[] = $trans;
                }

                return $this->sendResponse(["transactions" => $transactions]);
            }

        } catch (Exception $e) {
            Log::info($e);
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createATD(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "transactions" => "required|array"
            ]);
            $allowedDeductionIds = $this->getUserATDAllowedDeductionIds();
            if (empty($allowedDeductionIds)) {
                return $this->sendError(["message" => "You are not allowed to import these deductions."]);
            }
            $allowedDescriptions = Deduction::whereIn("id", $allowedDeductionIds)
                ->pluck("description")
                ->map(fn ($desc) => strtolower(trim($desc)))
                ->unique()
                ->values()
                ->all();
            $allowedDescriptionsMap = array_flip($allowedDescriptions);

            $groupedTransactions = [];
            foreach ($request->transactions as $transaction) {
                $transactionType = strtolower(trim($transaction["type"] ?? ""));
                if ($transactionType === "" || !isset($allowedDescriptionsMap[$transactionType])) {
                    return $this->sendError(["message" => "You are not allowed to import one or more ATD types."]);
                }

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
                if (!$deduction) {
                    return $this->sendError(["message" => "Deduction type not found for transaction."]);
                }

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

                $employeeDeduction = EmployeeDeduction::updateOrCreate([
                    "employee_id" => $employee->id,
                    "deduction_id" => $deduction->id,
                    "balance" => $transaction["amount"],
                    "transaction_number" => $transaction["transaction_number"],
                ],[
                    "months" => $terms,
                    "amount" => $transaction["amount"],
                    "source_of_fund" => "HO",
                    "start_date" => $dateStart,
                    "end_date" => $dateEnd,
                    "monthly_amortization" => $monthlyAmortization,
                    "remarks" => $remarks,
                    "debit_id" => $deduction->debit_id,
                    "credit_id" => $deduction->credit_id,
                    "branch_id" => $atdBranch->id,
                    "block_payment" => "Yes",
                    "approved_status" => "Yes",
                    "deduction_ref" => $deduction_reference,
                    "created_by" => $user->proper_full_name,
                    "date_created" => $transaction["transaction_date"],
                    "end_loan_status" => "No",
                    "source" => "CSHUB"
                ]);

                $branchHO = Branch::where('short_name', 'HO')
                ->first();
                $branchHOId = $branchHO->id;
                $branchHOName = $branchHO->name;
                $branchHONumber = $branchHO->branch_number;    

                $deductionEntry = Deduction::where('id', $employeeDeduction->deduction_id)->first();
                $debitId = $deductionEntry->debit_id;
                $creditId = $deductionEntry->credit_id;

                $entries = [];

                if ($employeeDeduction->wasRecentlyCreated && $employeeDeduction->amount > 0) {
                    $entryId = $this->generateEntryId($counterEntry);
                    $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
                    $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
                    $entries = array_merge($entries, [
                        [
                            'entry_id'            => $entryId,
                            'apsys_entry_id'      => $apsysEntryId,
                            'apsys_entry_main_id' => $apsysEntryMainId,
                            'document_no'         => $employee->employee_number . '|' . 'Head Office',
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'branch_name'         => $branchHOName,
                            'ven_no'              => $employee->employee_number,
                            'ven_name'            => $employee->full_name,
                            'amount'              => $employeeDeduction->amount,
                            'acc_no'              => $debitId,
                            'description_per_acc' => $employeeDeduction->deduction->description,
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => $deductionEntry->is_block_payment,
                        ],
                        [
                            'entry_id'            => $entryId,
                            'apsys_entry_id'      => $apsysEntryId,
                            'apsys_entry_main_id' => $apsysEntryMainId,
                            'document_no'         => $employee->employee_number . '|' . 'Head Office',
                            'branch_id'           => $branchHOId,
                            'branch_number'       => $branchHONumber,
                            'branch_name'         => $branchHOName,
                            'ven_no'              => $employee->employee_number,
                            'ven_name'            => $employee->full_name,
                            'amount'              => $employeeDeduction->amount,
                            'acc_no'              => $creditId,
                            'description_per_acc' => $employeeDeduction->deduction->description,
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => $deductionEntry->is_block_payment,
                        ]
                    ]);
                }

                $this->createEntries($entries, $user);

                $transactionNumber = $transaction["transaction_number"];

                $groupedTransactions[$transactionNumber][] = $transaction["employee_number"];
            }

            $successAll = true;

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

                if (!$response->successful()) {
                    $successAll = false;
                }
            }

            if ($successAll) {
                DB::commit();
                return $this->sendResponse(["message" => "ATD successfully created"]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Some ATD requests failed."]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e);
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
    private function createEntries($entries, $user)
    {
        if (empty($entries)) {
            return;
        }

        foreach ($entries as $entry) {

            Entry::create([
                'entry_id'                   => $entry['entry_id'],
                'document_no'                => $entry['document_no'],
                'branch_id'                  => $entry['branch_id'],
                'branch_number'              => $entry['branch_number'],
                'vendor_no'                  => $entry['ven_no'],
                'amount'                     => $entry['amount'],
                'account_no'                 => $entry['acc_no'],
                'description'                => $entry['description_per_acc'],
                'balance_type'               => $entry['balance_type'],
                'is_block_payment'           => $entry['is_block_payment'],
                'status'                     => 'Unapplied',
                'generated_at'               => now(),
                'generated_by'               => $user->id,
            ]);

            ApsysEntry::create([
                'ent_tran_no'       => $entry['apsys_entry_id'],
                'branch_id'         => $entry['branch_number'],
                'ent_post_date'     => now(),
                'ven_no'            => $entry['ven_no'],
                'ent_doc_no'        => $entry['document_no'],
                'ent_doc_date'      => now(),
                'ent_due_date'      => now(),
                'ent_header'        => '',
                'ent_crf_no'        => '',
                'ent_trans_amt'     => $entry['amount'],
                'ent_amt_due'       => $entry['amount'],
                'ent_module'        => 'Payroll',
                'acc_no'            => $entry['acc_no'],
                'ent_amt_per_acc'    => $entry['amount'],
                'ent_desc_per_acc'  => $entry['description_per_acc'],
                'ent_norm_bal'      => $entry['balance_type'],
                'ent_total'         => $entry['amount'],
                'ent_module_type'   => 'Single',
                'ent_app_amt'       => $entry['amount'],
                'ent_unapp_amt'     => $entry['amount'],
                'ent_vat_amt'       => 0,
                'ent_nonvat_amt'    => 0,
                'ent_na_amt'        => $entry['amount'],
                'ent_inp_tax_amt'   => 0,
                'ent_block_payment' => ($entry['is_block_payment'] == 1) ? 'Yes' : 'No',
                'ent_status'        => 'Unapplied',
                'ent_chk'           => 'No',
                'date_added'        => now(),
            ]);
        }

        $groupedEntries = collect($entries)->groupBy('apsys_entry_id');
        foreach ($groupedEntries as $mainId => $groupedEntry) {
            $entry = $groupedEntry->first();

            ApsysEntryMain::create([
                'ent_tran_no'       => $mainId,
                'ven_no'            => $entry['ven_no'],
                'ven_name'          => $entry['ven_name'],
                'ent_doc_date'      => now(),
                'branch_id'         => $entry['branch_number'],
                'branch_name'       => $entry['branch_name'],
                'ent_header'        => $entry['document_no'],
                'ent_trans_amt'     => $entry['amount'],
                'ent_post_date'     => now(),
                'ent_module_type'   => 'Single',
                'ent_status'        => 'Unapplied',
                'check_no'          => null,
                'ent_crf_no'        => '',
                'ent_doc_no'        => $entry['document_no'],
                'ent_module'        => 'Payroll',
                'ent_app_amt'       => $entry['amount'],
                'ent_unapp_amt'     => 0,
                'ent_block_payment' => ($entry['is_block_payment'] == 1) ? 'Yes' : 'No',
                'ent_due_date'      => now(),
            ]);
        }
    }
}
