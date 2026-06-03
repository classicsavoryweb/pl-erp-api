<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\EmployeeDeductionExport;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Accounting\Vendor;
use App\Models\EmployeeDeduction;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Deduction;
use App\Models\Entry;
use App\Support\EntryBranchNumber;
use App\Imports\EmployeeDeductionsImport;
use App\Models\Term;
use Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use DB;
use Log;
use Str;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class EmployeeDeductionsController extends Controller
{

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

        return 'PSD' . str_pad($counterEntry++, 8, '0', STR_PAD_LEFT)."-ERP";
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

        return 'PSD' . str_pad($counterApsysEntry++, 8, '0', STR_PAD_LEFT)."-ERP";
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

        return 'PSD' . str_pad($counterApsysEntryMain++, 8, '0', STR_PAD_LEFT)."-ERP";
    }

    private function getRowValue(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function normalizeNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9\.\-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }

        return (float) $clean;
    }

    private function parseImportDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = $this->excelDateToDateString($value);

        try {
            return Carbon::parse($normalized)->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    private function findEmployeeByNumber($value, int $padLength): ?Employee
    {
        $raw = trim((string) $value);
        $raw = preg_replace('/\s+/', '', $raw);

        if ($raw === '') {
            return null;
        }

        $candidates = [$raw];

        if (preg_match('/^0+/', $raw) === 0 && is_numeric($raw)) {
            $numeric = (int) $raw;
            $candidates[] = (string) $numeric;
            if ($padLength > 0) {
                $candidates[] = str_pad((string) $numeric, $padLength, '0', STR_PAD_LEFT);
            }
            $candidates[] = str_pad((string) $numeric, 7, '0', STR_PAD_LEFT);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        foreach ($candidates as $candidate) {
            $employee = Employee::where("employee_number", $candidate)->first();
            if ($employee) {
                return $employee;
            }
        }

        if (is_numeric($raw)) {
            $numeric = (int) $raw;
            $employee = Employee::whereRaw("CAST(employee_number AS UNSIGNED) = ?", [$numeric])->first();
            if ($employee) {
                return $employee;
            }
        }

        return null;
    }

    private function buildDuplicateCrfKey(int $employeeId, int $deductionId, string $transactionNumber): string
    {
        return $employeeId . '|' . $deductionId . '|' . Str::lower(trim($transactionNumber));
    }

    private function addDuplicateCrfRow(array &$duplicateRows, array $preparedRow, string $source): void
    {
        $duplicateKey = $preparedRow["row_number"] . '|' . $this->buildDuplicateCrfKey(
            $preparedRow["employee"]->id,
            $preparedRow["deduction"]->id,
            $preparedRow["transaction_number"]
        );

        if (!isset($duplicateRows[$duplicateKey])) {
            $duplicateRows[$duplicateKey] = [
                "row_number" => $preparedRow["row_number"],
                "crf_num" => $preparedRow["transaction_number"],
                "employee_number" => $preparedRow["employee"]->employee_number,
                "employee_name" => $preparedRow["employee"]->full_name,
                "deduction_id" => $preparedRow["deduction"]->id,
                "deduction_description" => $preparedRow["deduction"]->description,
                "duplicate_in" => [],
            ];
        }

        $duplicateRows[$duplicateKey]["duplicate_in"][$source] = $source === "file"
            ? "Uploaded file"
            : "Existing data";
    }

    public function getAllEmployeeDeductions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $deductions = EmployeeDeduction::query();

            if ($request->has("employee_id") && $request->employee_id) {
                $deductions = $deductions->where('employee_id', $request->employee_id);
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

            if ($request->has(["date_created_from", "date_created_to"])) {
                $deductions = $deductions->whereBetween('created_at', [$request->date_created_from ." 00:00:00", $request->date_created_to. " 23:59:59"]);
            }

            $deductions = $deductions->with(["employee", "employee.current_branch", "branch", "deduction"])
            ->whereHas("employee", function ($query) {
                $query->where("type", "Direct");
            });

            if ($request->has(["export"]) && $request->export) {
                $deductions = $deductions->get();
                return Excel::download(
                    new EmployeeDeductionExport($deductions),
                    "deductions.xlsx",
                    \Maatwebsite\Excel\Excel::XLSX
                );
            }

            $summaryQuery = clone $deductions;
            $summary = [
                "deduction_count" => (clone $summaryQuery)->where("balance", ">", 0)->count(),
                "total_balance" => (float) (clone $summaryQuery)->sum("balance"),
            ];

            $hasFilters = ($request->has("employee_id") && $request->employee_id)
                || ($request->has(["column_type", "key_word"]) && $request->column_type && $request->key_word)
                || ($request->has(["date_created_from", "date_created_to"]) && $request->date_created_from && $request->date_created_to);

            if ($hasFilters) {
                $deductions = $deductions
                    ->orderByRaw("CAST(balance AS DECIMAL(15,2)) DESC")
                    ->orderBy("start_date", "desc");
            } else {
                $deductions = $deductions->orderBy("start_date", "desc");
            }

            $deductions = $deductions->paginate($perPage);

            return $this->sendResponse([
                "deductions" => $deductions,
                "summary" => $summary,
            ]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllEmployeeDeductions(Request $request) {
        $request["export"] = true;

        return $this->getAllEmployeeDeductions($request);
    }

    public function importEmployeeDeductions(Request $request)
    {
        try {
            $request->validate([
                "deduction_file" => "required|file"
            ]);

            $import = new EmployeeDeductionsImport();
            Excel::import($import, $request->file("deduction_file"));

            $rows = $import->rows ?? collect();

            if ($rows->isEmpty()) {
                return $this->sendError(["message" => "No rows found in the uploaded file."]);
            }

            $deductions = Deduction::where("status", "active")->get();
            $deductionsById = $deductions->keyBy(fn ($deduction) => (string) $deduction->id);
            $deductionsByDescription = $deductions->keyBy(fn ($deduction) => strtolower(trim($deduction->description)));

            $branchHO = Branch::where("short_name", "HO")->first();
            $user = Auth::user();

            $padLength = (int) Employee::max(DB::raw("LENGTH(employee_number)"));
            if ($padLength <= 0) {
                $padLength = 7;
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            $preparedRows = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $rowData = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row;
                $hasValues = collect($rowData)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();

                if (!$hasValues) {
                    $skipped++;
                    continue;
                }

                $employeeIdRaw = $this->getRowValue($rowData, [
                    "employee_id",
                    "employee_number",
                    "emp_id",
                    "emp_no"
                ]);

                if ($employeeIdRaw === null || $employeeIdRaw === '') {
                    $errors[] = "Row {$rowNumber}: Employee ID is required.";
                    continue;
                }

                $employee = $this->findEmployeeByNumber($employeeIdRaw, $padLength);

                if (!$employee) {
                    $errors[] = "Row {$rowNumber}: Employee not found (" . trim((string) $employeeIdRaw) . ").";
                    continue;
                }

                $deductionIdRaw = $this->getRowValue($rowData, [
                    "deduction_id",
                    "deductionid"
                ]);
                $deductionDescRaw = $this->getRowValue($rowData, [
                    "deduction_description",
                    "deduction_desc",
                    "deduction"
                ]);

                $deduction = null;

                if ($deductionIdRaw !== null && $deductionIdRaw !== '') {
                    $normalizedId = ltrim((string) $deductionIdRaw, '0');
                    $normalizedId = $normalizedId === '' ? 0 : (int) $normalizedId;
                    if ($normalizedId > 0) {
                        $deduction = $deductionsById->get((string) $normalizedId);
                    }
                }

                if (!$deduction && $deductionDescRaw) {
                    $deduction = $deductionsByDescription->get(strtolower(trim((string) $deductionDescRaw)));
                }

                if (!$deduction) {
                    $errors[] = "Row {$rowNumber}: Deduction not found.";
                    continue;
                }

                $monthsRaw = $this->getRowValue($rowData, ["months", "month", "term"]);
                $monthsValue = $this->normalizeNumber($monthsRaw);
                if ($monthsValue === null) {
                    $errors[] = "Row {$rowNumber}: Months is required.";
                    continue;
                }
                $months = (int) $monthsValue;
                if ($months < 0) {
                    $months = 0;
                }

                $amountRaw = $this->getRowValue($rowData, ["amount", "loan_amount"]);
                $amountValue = $this->normalizeNumber($amountRaw);
                if ($amountValue === null) {
                    $errors[] = "Row {$rowNumber}: Amount is required.";
                    continue;
                }
                $amount = round($amountValue, 2);

                $startDateRaw = $this->getRowValue($rowData, [
                    "start_date_mm_dd_yyyy",
                    "start_date",
                    "start_date_mmddyyyy",
                    "date_start"
                ]);
                $startDate = $this->parseImportDate($startDateRaw);

                if (!$startDate) {
                    $errors[] = "Row {$rowNumber}: Start Date is required or invalid.";
                    continue;
                }

                $endDate = Carbon::parse($startDate)->addMonths($months)->format("Y-m-d");
                $monthlyAmortization = $months > 0 ? round($amount / $months, 2) : $amount;
                $balance = $amount;

                $remarks = trim((string) ($this->getRowValue($rowData, ["remarks", "remark"]) ?? ""));
                $transactionNumber = $this->getRowValue($rowData, ["crf_numr", "crf_num", "crf_number", "crf"]);
                $transactionNumber = $transactionNumber !== null ? trim((string) $transactionNumber) : null;
                if ($transactionNumber === '') {
                    $transactionNumber = null;
                }

                $branchId = $employee->branch_id ?? $employee->home_branch_id;
                if (!$branchId) {
                    $errors[] = "Row {$rowNumber}: Employee has no branch assigned.";
                    continue;
                }

                $preparedRows[] = [
                    "row_number" => $rowNumber,
                    "employee" => $employee,
                    "deduction" => $deduction,
                    "months" => $months,
                    "amount" => $amount,
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "monthly_amortization" => $monthlyAmortization,
                    "balance" => $balance,
                    "remarks" => $remarks,
                    "transaction_number" => $transactionNumber,
                    "branch_id" => $branchId,
                ];
            }

            $duplicateCrfRows = [];
            $fileCrfRows = [];

            foreach ($preparedRows as $preparedRow) {
                if (!$preparedRow["transaction_number"]) {
                    continue;
                }

                $duplicateKey = $this->buildDuplicateCrfKey(
                    $preparedRow["employee"]->id,
                    $preparedRow["deduction"]->id,
                    $preparedRow["transaction_number"]
                );

                $fileCrfRows[$duplicateKey][] = $preparedRow;
            }

            foreach ($fileCrfRows as $rowsWithSameCrf) {
                if (count($rowsWithSameCrf) <= 1) {
                    continue;
                }

                foreach ($rowsWithSameCrf as $preparedRow) {
                    $this->addDuplicateCrfRow($duplicateCrfRows, $preparedRow, "file");
                }
            }

            $preparedRowsWithCrf = array_values(array_filter($preparedRows, fn ($preparedRow) => !empty($preparedRow["transaction_number"])));

            if (!empty($preparedRowsWithCrf)) {
                $existingCrfLookup = [];

                $existingCrfRows = EmployeeDeduction::query()
                    ->select("employee_id", "deduction_id", "transaction_number")
                    ->whereIn("employee_id", array_values(array_unique(array_map(fn ($preparedRow) => $preparedRow["employee"]->id, $preparedRowsWithCrf))))
                    ->whereIn("deduction_id", array_values(array_unique(array_map(fn ($preparedRow) => $preparedRow["deduction"]->id, $preparedRowsWithCrf))))
                    ->whereIn("transaction_number", array_values(array_unique(array_map(fn ($preparedRow) => $preparedRow["transaction_number"], $preparedRowsWithCrf))))
                    ->get();

                foreach ($existingCrfRows as $existingCrfRow) {
                    $existingCrfLookup[$this->buildDuplicateCrfKey(
                        $existingCrfRow->employee_id,
                        $existingCrfRow->deduction_id,
                        $existingCrfRow->transaction_number
                    )] = true;
                }

                foreach ($preparedRowsWithCrf as $preparedRow) {
                    $duplicateKey = $this->buildDuplicateCrfKey(
                        $preparedRow["employee"]->id,
                        $preparedRow["deduction"]->id,
                        $preparedRow["transaction_number"]
                    );

                    if (isset($existingCrfLookup[$duplicateKey])) {
                        $this->addDuplicateCrfRow($duplicateCrfRows, $preparedRow, "system");
                    }
                }
            }

            if (!empty($duplicateCrfRows)) {
                $duplicateCrfRows = array_values(array_map(function ($duplicateCrfRow) {
                    $duplicateCrfRow["duplicate_in"] = array_values($duplicateCrfRow["duplicate_in"]);
                    return $duplicateCrfRow;
                }, $duplicateCrfRows));

                usort($duplicateCrfRows, fn ($firstRow, $secondRow) => $firstRow["row_number"] <=> $secondRow["row_number"]);

                $duplicateLineNumbers = array_values(array_unique(array_map(
                    fn ($duplicateCrfRow) => $duplicateCrfRow["row_number"],
                    $duplicateCrfRows
                )));
                $lineLabel = count($duplicateLineNumbers) === 1 ? "line" : "lines";
                $duplicateMessage = "Error: Duplicated CRF is detected on excel {$lineLabel} " . implode(", ", $duplicateLineNumbers);

                return $this->sendError([
                    "message" => $duplicateMessage,
                    "type" => "duplicate_crf",
                    "duplicates" => $duplicateCrfRows,
                    "errors" => $errors,
                ], 422);
            }

            $counterEntry = null;
            $counterApsysEntry = null;
            $counterApsysEntryMain = null;

            DB::beginTransaction();

            foreach ($preparedRows as $preparedRow) {
                $employee = $preparedRow["employee"];
                $deduction = $preparedRow["deduction"];
                $months = $preparedRow["months"];
                $amount = $preparedRow["amount"];
                $startDate = $preparedRow["start_date"];
                $endDate = $preparedRow["end_date"];
                $monthlyAmortization = $preparedRow["monthly_amortization"];
                $balance = $preparedRow["balance"];
                $remarks = $preparedRow["remarks"];
                $transactionNumber = $preparedRow["transaction_number"];
                $branchId = $preparedRow["branch_id"];

                $first_initial = strtoupper(substr($employee->first_name, 0, 1));
                $middle_initial = strtoupper(substr($employee->middle_name ?? '', 0, 1));
                $last_initial = strtoupper(substr($employee->last_name, 0, 1));
                $employee_number = $employee->employee_number;
                $base_reference = $first_initial . $middle_initial . $last_initial . $employee_number;

                $deduction_count = EmployeeDeduction::where("employee_id", $employee->id)
                    ->whereNotNull("deduction_ref")
                    ->where("deduction_ref", "!=", '')
                    ->count();

                $deductionIdPadded = str_pad($deduction->id, 8, '0', STR_PAD_LEFT);
                $deduction_reference = $base_reference . $deductionIdPadded . "|" . ($deduction_count + 1);

                $payload = [
                    "months" => $months,
                    "amount" => $amount,
                    "source_of_fund" => $branchHO?->short_name,
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "monthly_amortization" => $monthlyAmortization,
                    "balance" => $balance,
                    "remarks" => $remarks,
                    "debit_id" => $deduction->debit_id,
                    "credit_id" => $deduction->credit_id,
                    "branch_id" => $branchId,
                    "block_payment" => "No",
                    "approved_by" => $user?->proper_full_name,
                    "approved_date" => now(),
                    "approved_status" => "Yes",
                    "deduction_ref" => $deduction_reference,
                    "deduction_status" => "active",
                    "created_by" => $user?->proper_full_name,
                    "date_created" => date('Y-m-d'),
                    "end_loan_status" => "No",
                    "source" => "MANUAL",
                    "transaction_number" => $transactionNumber,
                ];

                $employeeDeduction = EmployeeDeduction::create(array_merge($payload, [
                    "employee_id" => $employee->id,
                    "deduction_id" => $deduction->id,
                ]));

                $created++;

                if ($employeeDeduction->wasRecentlyCreated && $employeeDeduction->amount > 0 && $branchHO) {
                    $branchHOId = $branchHO->id;
                    $branchHOName = $branchHO->name;
                    $branchHONumber = $branchHO->branch_number;

                    $entries = [];
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
                            'acc_no'              => $deduction->debit_id,
                            'description_per_acc' => $deduction->description,
                            'balance_type'        => 'Debit',
                            'is_block_payment'    => 0,
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
                            'acc_no'              => $deduction->credit_id,
                            'description_per_acc' => $deduction->description,
                            'balance_type'        => 'Credit',
                            'is_block_payment'    => 0,
                        ]
                    ]);

                    $this->createEntries($entries, $user);
                }
            }

            DB::commit();

            return $this->sendResponse([
                "message" => "Import completed. Created {$created}, updated {$updated}, skipped {$skipped}, errors " . count($errors) . ".",
                "created" => $created,
                "updated" => $updated,
                "skipped" => $skipped,
                "errors" => $errors,
            ]);
        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return $this->sendError(["message" => $e->getMessage()]);
        }
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

            $deductionId = str_pad($request->deduction_id, 8, '0', STR_PAD_LEFT);

            $deduction_reference = $base_reference . $deductionId . "|" . ($deduction_count + 1);

            $branch = Branch::where("name", "Head Office")->first();
            $user = Auth::user();

            $deduction_id = $request->deduction_id;

            $deduction = Deduction::where("id", $deduction_id)->first();

            $employeeDeduction = EmployeeDeduction::create([
                "employee_id" => $request->employee_id,
                "deduction_id" => $request->deduction_id,
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
                "created_by" => $user->proper_full_name,
                "date_created" => date('Y-m-d'),
                "end_loan_reason" => $request->end_loan_reason,
                "end_loan_status" => $request->end_loan_status,
                "source" => "MANUAL"
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

            if ($employeeDeduction->amount > 0) {
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

            $employeeDeduction = EmployeeDeduction::find($id);

            if (!$employeeDeduction) {
                return $this->sendError(["message" => "Employee deduction not found"]);
            }

            $employeeDeduction->update([
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

    public function getHealthCareForSalaryDeduction(Request $request) {
        try {
            $transactionLink = env('CS_HUB_URL_LINK')."/get-transactions";
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');
            $company = env('COMPANY_NAME');

            $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])->timeout(60)
            ->get($transactionLink,[
                "company" => $company,
                "type" => "SALARY_DEDUCTION",
                "approved_from" => "2020-01-01",
                "approved_to" => "2026-01-01"
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $transactions = [];

                foreach ($data['data']['transactions'] as $trans) {
                    $employee = Employee::where("employee_number", $trans["healthcare_transaction"]["user"]["employee_number"])->first();

                    if (!$employee) {
                        continue;
                    }

                    $trans["actual_liq_difference"] = abs($trans["actual_liq_difference"]);
                    $transactions[] = $trans;
                }

                return $this->sendResponse(["transactions" => $transactions]);
            }

            return $this->sendError(["message" => "Something went wrong"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createHealthCareSalaryDeductions(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "transactions" => "required|array"
            ]);

            $transactionLink = env('CS_HUB_URL_LINK')."/update-transaction-status";
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');
            $company = env('COMPANY_NAME');

            foreach ($request->transactions as $transaction) {
                $employee = Employee::where("employee_number", $transaction["healthcare_transaction"]["user"]["employee_number"])
                ->with(["current_branch"])
                ->first();

                if (!$employee) {
                    continue;
                }

                $first_initial = strtoupper(substr($employee->first_name, 0, 1));
                $middle_initial = strtoupper(substr($employee->middle_name ?? '', 0, 1));
                $last_initial = strtoupper(substr($employee->last_name, 0, 1));
                $employee_number = $employee->employee_number;

                $base_reference = $first_initial . $middle_initial . $last_initial . $employee_number;

                $deduction_count = EmployeeDeduction::where("employee_id", $employee->id)
                    ->whereNotNull("deduction_ref")
                    ->where("deduction_ref", "!=", '')
                    ->count();

                $deduction = Deduction::where("description", "Unliquidated CA")->first();

                if (!$deduction) {
                    return $this->sendError(["message" => "Deduction type not found for transaction."]);
                }

                $deductionId = str_pad($deduction->id, 8, '0', STR_PAD_LEFT);
                $deduction_reference = $base_reference . $deductionId . "|" . ($deduction_count + 1);
                $user = Auth::user();


                $monthlyAmortization = $transaction["actual_liq_difference"];

                $term = Term::where("amount_from", "<=", $transaction["actual_liq_difference"])
                ->where("amount_to", ">=", $transaction["actual_liq_difference"])
                ->first();

                $terms = $term->month;

                if ($terms == 0) {
                    $monthlyAmortization = $transaction["actual_liq_difference"];
                } else {
                    $monthlyAmortization = $transaction["actual_liq_difference"] / $terms;
                }

                $dateStart = now()->format("Y-m-d");
                $dateEnd = Carbon::parse($dateStart)->addMonths($terms)->format("Y-m-d");

                $remarks = $transaction["trans_no"]." | ".$transaction["verified_date"]." | ".$transaction["healthcare_transaction"]["healthcare_transaction_type"]["description"];

                $atdBranch = $employee->current_branch;

                $employeeDeduction = EmployeeDeduction::create([
                    "employee_id" => $employee->id,
                    "deduction_id" => $deduction->id,
                    "balance" => $transaction["actual_liq_difference"],
                    "transaction_number" => $transaction["trans_no"],
                    "months" => $terms,
                    "amount" => $transaction["actual_liq_difference"],
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
                    "date_created" => $transaction["verified_date"],
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

                // $response = Http::withBasicAuth($user, $password)
                // ->withHeaders([
                //     "Accept" => "application/json",
                //     "Content-Type" => "application/json"
                // ])->timeout(60)
                // ->post($transactionLink,[
                //     "transaction_number" => $transaction["trans_no"],
                //     "status" => "Deducted Liquidation",
                //     "type" => "SALARY_DEDUCTION",
                //     "employee_number" => $employee->employee_number,
                //     "company" => $company,
                //     "amount" => $transaction["actual_liq_difference"]
                // ]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Deduction successfully created"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function endLoanTransaction(Request $request, $id)
    {
        try {
            $request->validate([
                "end_loan_reason" => "required|string"
            ]);

            $amount = floatval(str_replace(',', '', $request->amount));
            $balance = floatval(str_replace(',', '', $request->balance));

            $updated_balance = $balance - $amount;

            $deduction = EmployeeDeduction::find($id);
            
            if (!$deduction) {
                return $this->sendError(["message" => "Deduction not found"]);
            }

            $deduction->update([
                "end_loan_reason" => $request->end_loan_reason,
                "end_loan_status" => "Yes",
                "balance" => 0
            ]);

            $employeeDeduction = EmployeeDeduction::find($id);

            $employee = Employee::where('id', $employeeDeduction->employee_id)->first();

            $branchHO = Branch::where('short_name', 'HO')->first();
            $branchHOId = $branchHO->id;
            $branchHOName = $branchHO->name;
            $branchHONumber = $branchHO->branch_number;

            $deductionEntry = Deduction::where('id', $employeeDeduction->deduction_id)->first();
            $endLoanDebitId = $deductionEntry->end_loan_debit_id;
            $endLoanCreditId = $deductionEntry->end_loan_credit_id;
            $endLoanVendorId = $deductionEntry->end_loan_vendor_id;

            $vendor = Vendor::where("ven_no", $deductionEntry->end_loan_vendor_id)->first();
            $vendorName = $vendor->ven_name;

            $user = Auth::user();

            $entries = [];

            if ($employeeDeduction->amount > 0) {
                $entryId = $this->generateEntryId($counterEntry);
                $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
                $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => 'End Loan Transaction' . '|' . now(),
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $endLoanVendorId,
                        'ven_name'            => $vendorName,
                        'amount'              => $employeeDeduction->amount,
                        'acc_no'              => $endLoanDebitId,
                        'description_per_acc' => 'End Loan Transaction' . '|' . now(),
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => 1,
                    ],
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => 'End Loan Transaction' . '|' . now(),
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $endLoanVendorId,
                        'ven_name'            => $vendorName,
                        'amount'              => $employeeDeduction->amount,
                        'acc_no'              => $endLoanCreditId,
                        'description_per_acc' => 'End Loan Transaction' . '|' . now(),
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => 1,
                    ]
                ]);
            }

            $this->createEntries($entries, $user);

            return $this->sendResponse(["message" => "End loan updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    private function createEntries($entries, $user)
    {
        if (empty($entries)) {
            return;
        }

        $entries = EntryBranchNumber::normalizeEntriesForCurrentCompany($entries);

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
                'ent_amt_per_acc'   => $entry['amount'],
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
