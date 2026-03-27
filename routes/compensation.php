<?php

use App\Http\Controllers\Compensation\ATDController;
use App\Http\Controllers\Compensation\BranchAdditionalIncomeController;
use App\Http\Controllers\Compensation\ConverterController;
use App\Http\Controllers\Compensation\CutOffController;
use App\Http\Controllers\Compensation\EmployeeDeductionsController;
use App\Http\Controllers\Compensation\DeductionHistoryController;
use App\Http\Controllers\Compensation\EntryController;
use App\Http\Controllers\Compensation\LastPayController;
use App\Http\Controllers\Compensation\PayrollController;
use App\Http\Controllers\Compensation\PayrollListController;
use App\Http\Controllers\Compensation\PayrollProofListController;
use App\Http\Controllers\Compensation\PayslipController;
use App\Http\Controllers\Compensation\PayrollListBankDetailsController;
use Illuminate\Support\Facades\Route;

//compensation routes
Route::middleware('auth:sanctum')->group( function () {

    //payroll list
    Route::get("/get-all-payroll-list",[PayrollListController::class, "getAllPayrollList"]);
    Route::get("/get-bank-details/{id}",[PayrollListController::class, "getBankDetails"]);
    Route::get("/export-all-payroll-list",[PayrollListController::class, "exportPayrollList"]);
    Route::middleware(["permission:perform_compensation_payroll_list"])->post("/create-payroll-list", [PayrollListController::class, "createPayrollList"]);
    Route::middleware(["permission:perform_compensation_payroll_list"])->post("/edit-payroll-list/{id}", [PayrollListController::class, "editPayrollList"]);

    //cutoff
    Route::get("/get-all-cut-off",[CutOffController::class, "getAllCutOffs"]);
    Route::get("/get-all-finalized-cut-offs",[CutOffController::class, "getAllFinalizedCutOffs"]);
    Route::get("/get-active-cut-off", action: [CutOffController::class, "getActiveCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/create-cut-off", [CutOffController::class, "createCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/edit-cut-off/{id}", [CutOffController::class, "editCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->get("/initialize-cut-off/{id}", [CutOffController::class, "initializeCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->get("/finalize-cut-off/{id}", [CutOffController::class, "finalizeCutOff"]);
    Route::middleware(["permission:perform_compensation_cutoff_setup"])->post("/generate-payroll", [PayrollController::class, "generateBranchPayroll"]);

    //employee deductions
    Route::get("/get-all-employee-deductions", [EmployeeDeductionsController::class, "getAllEmployeeDeductions"]);
    Route::get("/get-employee-deductions", [EmployeeDeductionsController::class, "getEmployeeDeductions"]);
    Route::middleware(["permission:export_all_compensation_deductions"])->get("/export-all-employee-deductions", [EmployeeDeductionsController::class, "exportAllEmployeeDeductions"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/create-employee-deduction", [EmployeeDeductionsController::class, "createEmployeeDeduction"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/edit-employee-deduction/{id}", [EmployeeDeductionsController::class, "editEmployeeDeduction"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/import-employee-deductions", [EmployeeDeductionsController::class, "importEmployeeDeductions"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/end-loan-transaction/{id}", [EmployeeDeductionsController::class, "endLoanTransaction"]);
    Route::get("/get-all-employee-atds", [ATDController::class, "getAllEmployeeDeductions"]);
    Route::get("/get-atd-allowed-deductions", [ATDController::class, "getAllowedATDDeductions"]);
    Route::get("/get-cshub-atds", [ATDController::class, "getCSHubATD"]);
    Route::middleware(["permission:perform_compensation_atd"])->post("/create-cshub-atds", [ATDController::class, "createATD"]);
    Route::middleware(["permission:export_compensation_atd"])->get("/export-all-atds", [ATDController::class, "exportAllEmployeeDeductions"]);

    //payroll
    Route::get("/get-all-payroll", [PayrollController::class, "getAllPayroll"]);
    Route::get("/get-payroll/{id}", [PayrollController::class, "getPayrollDetails"]);
    Route::get('/payroll/export-bank-details', [PayrollListBankDetailsController::class, 'exportBankDetails']);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/update-payroll-deduction/{payrollId}", [PayrollController::class, "updateCompanyDeductions"]);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/update-payroll-details/{payrollId}", [PayrollController::class, "updatePayrollDetails"]);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/update-payroll-contributions/{payrollId}", [PayrollController::class, "updatePayrollContributions"]);
    Route::middleware(["permission:perform_compensation_incentives"])->post("/update-payroll-incentive/{payrollId}", [PayrollController::class, "updatePayrollIncentive"]);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/edit-payroll-deduction-details/{payrollId}", [PayrollController::class, "editCompanyDeductions"]);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/update-payroll-timekeeping-branch/{payrollId}", [PayrollController::class, "updatePayrollTimekeepingBranch"]);
    Route::post("/preview-payroll-compution/{id}", [PayrollController::class, "showPayrollComputation"]);

    //converter transactions
    Route::get("/get-all-converter-details", [ConverterController::class, "getAllConverterDetails"]);
    Route::get("/get-all-converter-main", [ConverterController::class, "getAllConverterMain"]);
    Route::middleware(["permission:perform_compensation_converter"])->post("/create-converter-details", [ConverterController::class, "createConverterDetails"]);
    Route::middleware(["permission:perform_compensation_converter"])->post("/edit-converter-details/{detailId}", [ConverterController::class, "editConverterDetails"]);
    Route::middleware(["permission:perform_compensation_converter"])->get("/void-converter-details/{detailId}", [ConverterController::class, "voidConverterDetails"]);
    Route::middleware(["permission:perform_compensation_converter"])->post("/import-converter-transactions", [ConverterController::class, "importTransactions"]);
    Route::get("/export-converter-details", [ConverterController::class, "exportConverterDetails"]);

    //converter batch
    Route::middleware(["permission:perform_compensation_converter"])->post("/create-converter-main", [ConverterController::class, "createConverterMain"]);
    Route::get("/get-transactions", [ConverterController::class, "getTransactions"]);
    Route::get("/void-batch-convereter/{batchId}", [ConverterController::class, "voidBatch"]);
    Route::get("/post-batch-convereter/{batchId}", [ConverterController::class, "postBatch"]);
    Route::get("/generate-converter/{batchId}", [ConverterController::class, "generateConverter"]);
    Route::get("/get-cshub-applied-transactions", [ConverterController::class, "getCSHubAppliedTransactions"]);
    Route::middleware(["permission:perform_compensation_converter"])->post("/create-cshub-applied-transactions", [ConverterController::class, "createCSHubTransactions"]);
    Route::middleware(["permission:perform_compensation_converter"])->get("/send-transaction-email/{batchId}", [ConverterController::class, "sendTransactionEmail"]);
    Route::middleware(["permission:perform_compensation_converter"])->post("/resend-transaction-email/{batchId}", [ConverterController::class, "resendTransactionEmail"]);
    Route::get("/export-converter-batch-transactions", [ConverterController::class, "exportConverterMainTransactions"]);

    //prooflist
    Route::get("/get-all-payroll-prooflist", [PayrollProofListController::class, "getProofList"]);
    Route::middleware(["permission:perform_compensation_payroll_prooflist"])->get("/generate-payroll-prooflist-converter", [PayrollProofListController::class, "generateConverterForProoflist"]);
    Route::middleware(["permission:perform_compensation_payroll_prooflist"])->get("/export-payroll-prooflist-converter", [PayrollProofListController::class, "exportProoflist"]);

    //entries
    Route::get("/get-all-entries", [EntryController::class, "getAllEntries"]);
    Route::middleware(["permission:perform_compensation_entries"])->post("/generate-entries", [EntryController::class, "generateEntries"]);
    Route::middleware(["permission:perform_compensation_entries"])->post("/generate-entries-for-review", [EntryController::class, "generateEntriesForReview"]);
    Route::middleware(["permission:export_all_compensation_entries"])->get("/export-all-entries", [EntryController::class, "exportAllEntries"]);

    //last pay
    Route::get("/get-all-last-pay", [LastPayController::class, "getAllLastPay"]);
    Route::get("/get-all-holded-employees", [LastPayController::class, "getHoldedEmployees"]);
    Route::get("/get-initial-last-pay/{employeeNumber}", [LastPayController::class, "getEmployeeInitialLastPayDetails"]);
    Route::middleware(["permission:perform_compensation_last_pay"])->post("/create-last-pay/{employeeNumber}", [LastPayController::class, "createLastPayRecord"]);
    Route::middleware(["permission:perform_compensation_last_pay"])->post("/edit-last-pay/{lastPayId}", [LastPayController::class, "editLastPayRecord"]);
    Route::middleware(["permission:perform_compensation_last_pay"])->post("/remove-last-pay-deduction/{employeeNumber}", [LastPayController::class, "removeLastPayDeduction"]);
    Route::middleware(["permission:perform_compensation_last_pay"])->post("/edit-last-pay-deduction-details/{lastPayDeductionId}", [LastPayController::class, "editLastPayDeductionDetails"]);
    Route::get("/post-last-pay/{lastPayId}", [LastPayController::class, "postLastPay"]);

    //additional income
    Route::get("/get-all-branch-additional-income", [BranchAdditionalIncomeController::class, "getAllBranchAdditionalIncome"]);
    Route::middleware(["permission:perform_compensation_additional_income"])->post("/save-branch-additional-income", [BranchAdditionalIncomeController::class, "updateOrCreateIncome"]);
    Route::middleware(["permission:perform_compensation_additional_income"])->post("/import-branch-additional-income", [BranchAdditionalIncomeController::class, "importBranchAdditionalIncome"]);
    Route::middleware(["permission:perform_compensation_additional_income"])->get("/export-branch-additional-income", [BranchAdditionalIncomeController::class, "exportBranchAdditionalIncome"]);

});
