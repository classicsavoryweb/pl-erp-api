<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\ConverterDetailsExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessConverterDetailImport;
use App\Jobs\SendTransactionToEmployeeJob;
use App\Models\ConverterDetail;
use App\Models\ConverterMain;
use App\Models\CSHubModule;
use App\Models\CSHubSourceType;
use App\Models\CSHubTransactionType;
use App\Models\Employee;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use DOMDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use ZipArchive;

class ConverterController extends Controller
{
    public function getAllConverterDetails(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;
            $converters = ConverterDetail::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $converters = $converters->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $converters = $converters->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $converters = $converters->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["date_from", "date_to"])) {
                $converters = $converters->whereBetween("created_at", [
                    Carbon::parse($request->date_from)->startOfDay(),
                    Carbon::parse($request->date_to)->endOfDay(),
                ]);
            }

            if ($request->has(["status"])) {
                $converters = $converters->where("status", $request->status);
            }

            if ($request->has(["branch_id"])) {
                $converters = $converters->where("branch_id", $request->branch_id);
            }

            if ($request->has(["converter_main_id"])) {
                $converters = $converters->with(["employee", "transaction_type", "branch", "admin_added", "converter_main"])
                ->where("converter_main_id", $request->converter_main_id)
                ->orderBy(
                    Employee::select('last_name')->whereColumn('employees.id', 'converter_details.employee_id'),
                    'ASC'
                )
                ->get();

                return Excel::download(new ConverterDetailsExport($converters), "converter_details.xls");
            }

            if ($request->has(["converter_main_ids"])) {
                $converters = $converters->with(["employee", "transaction_type", "branch", "admin_added", "converter_main"])
                ->whereIn("converter_main_id", $request->converter_main_ids)
                ->orderBy(
                    Employee::select('last_name')->whereColumn('employees.id', 'converter_details.employee_id'),
                    'ASC'
                )
                ->get();

                return Excel::download(new ConverterDetailsExport($converters), "converter_details.xls");
            }

            $converters = $converters->with(["employee", "transaction_type", "branch", "admin_added", "converter_main"])            
            ->orderBy("created_at", "DESC");

            if ($request->has(["export"]) && $request->export) {
                $converters = $converters->get();
                return Excel::download(new ConverterDetailsExport($converters), "converter_details.xls");
            }

            $converters = $converters->paginate($perPage);

            return $this->sendResponse($converters);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportConverterDetails(Request $request) {
        $request["export"] = true;

        return $this->getAllConverterDetails($request);
    }

    public function getAllConverterMain(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $converters = ConverterMain::query();
            
            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    $converters = $converters->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                        $query->where($relatedColumn, 'LIKE', "%$keyword%");
                    });

                } else {
                    $converters = $converters->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $converters = $converters->where("status", $request->status);
            }

            if ($request->has(["date_from", "date_to"])) {
                $converters = $converters->whereBetween("created_at", [
                    Carbon::parse($request->date_from)->startOfDay(),
                    Carbon::parse($request->date_to)->endOfDay(),
                ]);
            }

            if ($request->has(["export"]) && $request->export) {
                $converters = $converters->select("id")
                ->get()
                ->toArray();

                $request["converter_main_ids"] = $converters;

                return $this->getAllConverterDetails($request);
            }

            $converters = $converters->with([
                "added_by",
                "posted_by",
                "generated_by",
                "voided_by",
                "bank_account_type",
                "converter_details" => function ($q) {
                    $q->join('employees', 'employees.id', '=', 'converter_details.employee_id')
                    ->orderBy('employees.last_name', 'ASC')
                    ->select('converter_details.*');
                },
                "converter_details.employee",
                "converter_details.transaction_type"
            ])
            ->orderBy("created_at", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($converters);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportConverterMainTransactions(Request $request) {
        $request["export"] = true;

        return $this->getAllConverterMain($request);
    }

    public function createConverterDetails(Request $request) {
        try {
            DB::beginTransaction();

            $request->validate([
                "employee_id" => "required",
                "amount" => "required",
                "transaction_type_id" => "required",
                "terms_id" => "required"
            ]);

            $employee = Employee::with(["latest_bank_details"])->find($request->employee_id);
            $terms = Term::find($request->terms_id);

            $bankAccount = $employee->latest_bank_details;

            ConverterDetail::create([
                "employee_id" => $employee->id,
                "branch_id" => $employee->current_branch->id,
                "amount" => $request->amount,
                "transaction_type_id" => $request->transaction_type_id,
                "terms" => $terms->cutoff,
                "remarks" => $request->remarks ?? null,
                "added_by" => Auth::user()->id,
                "status" => "Pending",
                "source" => "Manual",
                "bank_account_number" => $bankAccount->bank_number
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Converter details successfully created"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editConverterDetails(Request $request, $detailId) {
        try {
            DB::beginTransaction();

            $request->validate([
                "amount" => "required",
            ]);

            $detail = ConverterDetail::find($detailId);

            if ($detail) {
                if (strtoupper($detail->source) == "MANUAL") {
                    $detail->update([
                        "amount" => $request->amount,
                        "transaction_type_id" => $request->transaction_type_id,
                        "remarks" => $request->remarks ?? null
                    ]);
                } else {
                    $detail->update([
                        "amount" => $request->amount,
                        "remarks" => $request->remarks ?? null
                    ]);
                }

                if ($detail->converter_main_id != null) {
                    $converter = ConverterMain::with(["converter_details"])->find($detail->converter_main_id);

                    if ($converter) {
                        $transactions = $converter->converter_details;

                        $amount = 0;
                        $count = $transactions->count();
                        foreach ($transactions as $trans) {
                            $amount += $trans->amount;
                        }

                        $converter->update([
                            "amount" => $amount,
                            "count" => $count,
                        ]);
                    }
                }

                DB::commit();
                return $this->sendResponse(["message" => "Converter details updated successfully"]);
            }

            return $this->sendError(["message" => "Converter details not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function voidConverterDetails($detailId) {
        try {
            DB::beginTransaction();

            $detail = ConverterDetail::find($detailId);

            if ($detail) {
                $detail->update([
                    "status" => "Voided"
                ]);

                DB::commit();
                return $this->sendResponse(["message" => "Converter details voided successfully"]);
            }

            return $this->sendError(["message" => "Converter details not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getTransactions(Request $request) {
        try {
            DB::beginTransaction();

            $transactions = ConverterDetail::query();
            
            if ($request->has(["transaction_type_id"])) {
                $transactions = $transactions->where("transaction_type_id", $request->transaction_type_id);
            }

            if ($request->has(["batch_number"])) {
                $transactions = $transactions->where("batch_number", $request->batch_number);
            } else {
                $transactions = $transactions->where("status", $request->status)
                ->whereHas("employee", function ($query) use ($request) {
                    $query->whereHas("latest_bank_details", function ($q) use ($request) {
                        $q->where("bank_account_type_id", $request->bank_account_type_id);
                    });
                });
            }

            $transactions = $transactions->with(["employee", "transaction_type", "branch"])
            ->where(function ($q) {
                $q->whereDoesntHave("converter_main")
                ->orWhereHas("converter_main", function ($sub) {
                    $sub->where("status", "Voided");
                });
            })
            ->orderBy("created_at", "DESC")
            ->get();

            DB::commit();
            return $this->sendResponse([
                "transactions" => $transactions,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createConverterMain(Request $request) {
        try {
            DB::beginTransaction();

            $request->validate([
                "selected_transactions" => "required",
                "bank_account_type_id" => "required"
            ]);

            $transactions = ConverterDetail::with([
                "employee",
                "employee.payroll_list",
                "transaction_type"
            ])
            ->whereIn("id", $request->selected_transactions)->get();
            $total_amount = 0;
            $excludedTransactions = [];
            $includedTransactions = [];
            $hasExcludedTransaction = false;

            foreach ($transactions as $trans) {
                $employee = $trans->employee;
                $payrollList = $employee->payroll_list;

                if (
                    $payrollList->is_hold == 0 &&
                    $payrollList->is_bank == 1 &&
                    $employee->status == "active"
                ) {
                    $total_amount += $trans->amount;
                    $includedTransactions[] = $trans;
                } else {
                    $excludedTransactions[] = $trans;
                    $hasExcludedTransaction = true;
                }
            }

            if (count($includedTransactions) > 0) {
                $batch = ConverterMain::create([
                    "count" => count($includedTransactions),
                    "total_amount" => $total_amount,
                    "added_by" => Auth::user()->id,
                    "status" => "Pending",
                    "bank_account_type_id" => $request->bank_account_type_id,
                    "transaction_type_id" => $request->transaction_type_id ?? null
                ]);
    
                foreach ($includedTransactions as $trans) {
                    $trans->update([
                        "converter_main_id" => $batch->id
                    ]);
                }
    
                DB::commit();
                return $this->sendResponse([
                    "has_excluded_transactions" => $hasExcludedTransaction,
                    "transactions" => $excludedTransactions,
                    "message" => "Batch successfully created"
                ]);
            }

            return $this->sendError([
                "has_excluded_transactions" => $hasExcludedTransaction,
                "transactions" => $excludedTransactions,
                "message" => "Batch creation failed"
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function generateConverter(Request $request, $batchId) {
        try {
            DB::beginTransaction();
            $converterBatch = ConverterMain::find($batchId);

            if ($converterBatch) {
                $transactions = $converterBatch->converter_details()
                ->with(["employee", "employee.latest_bank_details", "transaction_type"])
                ->get();

                if ($converterBatch->status != "Generated") {
                    $converterBatch->update([
                        "status" => "Generated",
                        "generated_by" => Auth::user()->id,
                        "generated_at" => now(),
                    ]);
                }

                $converterToUse = $converterBatch->bank_account_type_id == 1 ? "CASH_CARD_CONVERTER" : "SAVINGS_CONVERTER";
                $templatePath = public_path('converter/'.$converterToUse.'.xlsm');
                $tempCopyPath = storage_path('app/public/'.$converterBatch->batch_number.'.xlsm');

                $company = env('COMPANY_FULL_NAME');
                $filePrefix =  env('COMPANY_NAME') == "SFI" ? "TPAYSAVORY" : "TPAYTONYTI";
                $virtualAccount =  env('COMPANY_NAME') == "SFI" ? "9049948528" : "9081473203";
                $companyCode = env('COMPANY_NAME') =="SFI" ? "X48Z" : "Y490";
                $batch = $converterBatch->id;
                $creditingDate = now()->format("Y-m-d");

                copy($templatePath, $tempCopyPath);

                $zip = new ZipArchive;
                if ($zip->open($tempCopyPath) === TRUE) {
                    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

                    $dom = new DOMDocument();
                    $dom->loadXML($sheetXml);
                    $sheetData = $dom->getElementsByTagName('sheetData')->item(0);

                    $this->writeCell($dom, $sheetData, 'A1', (string)$company);
                    if ($converterBatch->bank_account_type_id == 1) {
                        $this->writeCell($dom, $sheetData, 'B2', (string)$filePrefix);
                        $this->writeCell($dom, $sheetData, 'B3', (string)$virtualAccount);
                        $this->writeCell($dom, $sheetData, 'B4', (string)$creditingDate);
                        $this->writeCell($dom, $sheetData, 'B5', (string)$batch);
                    } else {
                        $this->writeCell($dom, $sheetData, 'B5', (string)$batch);
                        $this->writeCell($dom, $sheetData, 'B2', (string)$creditingDate);
                        $this->writeCell($dom, $sheetData, 'B3', (string)$companyCode);
                    }

                    $rowIndex = 7;
                    foreach ($transactions as $transaction) {

                        $transaction->update([
                            "status" => "Generated"
                        ]);

                        $colA = "A".$rowIndex;
                        $colB = "B".$rowIndex;
                        $colC = "C".$rowIndex;
                        $colE = "E".$rowIndex;

                        $transactionDescription = $transaction->transaction_type->description ?? "";

                        $bankNumber = $transaction->bank_account_number ?? $transaction->employee->latest_bank_details->bank_number;

                        $this->writeCell($dom, $sheetData, $colA, (string)$bankNumber);
                        $this->writeCell($dom, $sheetData, $colB,number_format($transaction->amount, 2));
                        $this->writeCell($dom, $sheetData, $colC, (string)$transaction->employee->full_name);
                        $this->writeCell($dom, $sheetData, $colE, (string)$transactionDescription);

                        $rowIndex++;
                    }

                    $newXml = $dom->saveXML();
                    $zip->addFromString('xl/worksheets/sheet1.xml', $newXml);
                    $zip->close();
                }

                DB::commit();
                return response()->download($tempCopyPath, $converterBatch->batch_number.'.xlsm')->deleteFileAfterSend(true);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Batch not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function voidBatch($batchId) {
        try {
            DB::beginTransaction();

            $batch = ConverterMain::find($batchId);

            if ($batch) {
                $batch->update([
                    "status" => "Voided",
                    "voided_by" => Auth::user()->id,
                    "voided_at" => now()
                ]);

                DB::commit();
                return $this->sendResponse(["message" => "Batch voided successfully"]);
            }

            DB::rollBack();
            return $this->sendError(["message" => "Batch not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getCSHubAppliedTransactions(Request $request) {
        try {
            $request->validate([
                "type" => "required",
                "posted_from" => "required",
                "posted_to" => "required"
            ]);

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
                "type" => $request->type,
                "posted_from" => $request->posted_from,
                "posted_to" => $request->posted_to
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $transactions = [];

                foreach ($data['data']['transactions'] as $trans) {
                    $isExisting = ConverterDetail::where("transaction_number", $trans["trans_no"])
                    ->latest()->first();

                    if ($isExisting && in_array($isExisting->status, ['Pending', 'Generated'])) {
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

    public function createCSHubTransactions(Request $request) {
        try {
            DB::beginTransaction();

            $request->validate([
                "selected_transactions" => "required|array",
            ]);

            foreach ($request->selected_transactions as $transaction) {
                $employee = Employee::with(["current_branch"])
                ->where("employee_number", $transaction["employee_number"])
                ->first();

                $source = CSHubSourceType::where("description", $transaction["transaction_type"])->first();
                $module = CSHubModule::where("description", $transaction["type"])->first();

                $transactionType = CSHubTransactionType::where("transaction_type", $source->id)
                // ->where("credit_status_positive", $transaction["for_crediting"])
                // ->where("deduction_status_positive", $transaction["for_liquidation"])
                ->where("source_id", $module->id);

                if ($transaction["type"] === "HEALTHCARE" && isset($transaction["exam_type"])) {
                    $examType = CSHubSourceType::where("description", $transaction["exam_type"])
                    ->where("source_type", "Exam")
                    ->first();

                    if (!$examType) {
                        $examType = CSHubSourceType::where("description", "LIKE", "%".$transaction["exam_type"]."%")
                        ->where("source_type", "Exam")
                        ->first();
                    }

                    if ($examType) {
                        $transactionType = $transactionType->where("exam_type", $examType->id);
                    } else {
                        $transactionType = $transactionType->where("credit_status_positive", $transaction["for_crediting"])
                        ->where("deduction_status_positive", $transaction["for_liquidation"]);
                    }
                } else if ($transaction["type"] === "HEALTHCARE" && !isset($transaction["exam_type"])) {
                    $transactionType = $transactionType->where("credit_status_positive", $transaction["for_crediting"])
                    ->where("deduction_status_positive", $transaction["for_liquidation"]);
                }
                
                $transactionType = $transactionType->first();

                $isExisting = ConverterDetail::where("transaction_number", $transaction["transaction_number"])
                ->where("employee_id", $employee->id)
                ->whereNot("status", "Voided")
                ->first();

                if ($isExisting) {
                    continue;
                }

                ConverterDetail::create([
                    "employee_id" => $employee->id,
                    "branch_id" => $employee->current_branch->id,
                    "status" => "Pending",
                    "source" => "CSHUB",
                    "terms" => $transaction["terms"],
                    "transaction_number" => $transaction["transaction_number"],
                    "amount" => $transaction["amount"],
                    "remarks" => $transaction["purpose"],
                    "transaction_type_id" => $transactionType->id,
                    "added_by" => Auth::user()->id
                ]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Transactions successfully created"]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e);
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function sendTransactionEmail ($batchId) {
        try {
            $batch = ConverterMain::find($batchId);

            if ($batch) {
                SendTransactionToEmployeeJob::dispatch($batch, Auth::user()->id, [], false);

                $batch->update([
                    "status" => "Sent",
                ]);

                return $this->sendResponse(["message" => "Transaction email sending"]);
            }

            return $this->sendError(["message" => "Batch not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function resendTransactionEmail ($batchId, Request $request) {
        try {
            $request->validate([
                "selected_transactions" => "required"
            ]);

            $batch = ConverterMain::find($batchId);

            if ($batch) {
                SendTransactionToEmployeeJob::dispatch($batch, Auth::user()->id, $request->selected_transactions, true);

                return $this->sendResponse(["message" => "Transaction email resending"]);
            }

            return $this->sendError(["message" => "Batch not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function postBatch ($batchId) {
        try {
            $batch = ConverterMain::find($batchId);

            if ($batch && $batch->status === "Pending") {                
                $batch->update([
                    "status" => "Posted",
                    "posted_by" => Auth::user()->id,
                    "posted_at" => now()
                ]);

                return $this->sendResponse(["message" => "Batch updated successfully"]);
            }

            return $this->sendError(["message" => "Batch not found"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function importTransactions (Request $request) {
        try {
            $request->validate([
                "transactions_file" => "required"
            ]);

            $filePath = null;

            if ($request->hasFile("transactions_file")) {
                $storage = Storage::disk("hr_system_folder");
                $directory = "/converter/imports";
                $file = $request->file("transactions_file");

                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));

                ProcessConverterDetailImport::dispatch($filePath, Auth::user()->id);
                $user = Auth::user();
                activity()->causedBy($user)->useLog("Import Transactions")->log('User imported converter transactions');
                return $this->sendResponse(["message" => "Excel file imported successfully"]);
            }

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
