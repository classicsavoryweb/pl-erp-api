<?php

use App\Http\Controllers\Compensation\ConverterController;
use App\Http\Controllers\Compensation\CutOffController;
use App\Http\Controllers\Compensation\EmployeeDeductionsController;
use App\Http\Controllers\Compensation\DeductionHistoryController;
use App\Http\Controllers\Compensation\EntryController;
use App\Http\Controllers\Compensation\PayrollController;
use App\Http\Controllers\Compensation\PayrollListController;
use App\Http\Controllers\Compensation\PayrollProofListController;
use Illuminate\Support\Facades\Route;

//compensation routes
Route::middleware('auth:sanctum')->group( function () {

    //payroll list
    Route::get("/get-all-payroll-list",[PayrollListController::class, "getAllPayrollList"]);
    Route::middleware(["permission:perform_compensation_payroll_list"])->post("/create-payroll-list", [PayrollListController::class, "createPayrollList"]);
    Route::middleware(["permission:perform_compensation_payroll_list"])->post("/edit-payroll-list/{id}", [PayrollListController::class, "editPayrollList"]);

    //cutoff
    Route::get("/get-all-cut-off",[CutOffController::class, "getAllCutOffs"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/create-cut-off", [CutOffController::class, "createCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/edit-cut-off/{id}", [CutOffController::class, "editCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->get("/initialize-cut-off/{id}", [CutOffController::class, "initializeCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->get("/finalize-cut-off/{id}", [CutOffController::class, "finalizeCutOff"]);

    //employee deductions
    Route::get("/get-all-employee-deductions", [EmployeeDeductionsController::class, "getAllEmployeeDeductions"]);
    Route::get("/get-employee-deductions", [EmployeeDeductionsController::class, "getEmployeeDeductions"]);
    Route::middleware(["permission:export_all_compensation_deductions"])->get("/export-all-employee-deductions", [EmployeeDeductionsController::class, "exportAllEmployeeDeductions"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/create-employee-deduction", [EmployeeDeductionsController::class, "createEmployeeDeduction"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/edit-employee-deduction/{id}", [EmployeeDeductionsController::class, "editEmployeeDeduction"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/end-loan-transaction/{id}", [EmployeeDeductionsController::class, "endLoanTransaction"]);

    //payroll
    Route::get("/get-all-payroll", [PayrollController::class, "getAllPayroll"]);
    Route::get("/get-payroll/{id}", [PayrollController::class, "getPayrollDetails"]);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/update-payroll-deduction/{payrollId}", [PayrollController::class, "updateCompanyDeductions"]);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/update-payroll-details/{payrollId}", [PayrollController::class, "updatePayrollDetails"]);
    Route::middleware(["permission:perform_compensation_incentives"])->post("/update-payroll-incentive/{payrollId}", [PayrollController::class, "updatePayrollIncentive"]);
    Route::middleware(["permission:perform_compensation_payroll"])->post("/edit-payroll-deduction-details/{payrollId}", [PayrollController::class, "editCompanyDeductions"]);

    //converter transactions
    Route::get("/get-all-converter-details", [ConverterController::class, "getAllConverterDetails"]);
    Route::get("/get-all-converter-main", [ConverterController::class, "getAllConverterMain"]);
    Route::middleware(["permission:perform_compensation_converter"])->post("/create-converter-details", [ConverterController::class, "createConverterDetails"]);
    Route::middleware(["permission:perform_compensation_converter"])->post("/edit-converter-details/{detailId}", [ConverterController::class, "editConverterDetails"]);
    Route::middleware(["permission:perform_compensation_converter"])->get("/void-converter-details/{detailId}", [ConverterController::class, "voidConverterDetails"]);
    
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
    //prooflist
    Route::get("/get-all-payroll-prooflist", [PayrollProofListController::class, "getProofList"]);
    Route::middleware(["permission:perform_compensation_payroll_prooflist"])->get("/generate-payroll-prooflist-converter", [PayrollProofListController::class, "generateConverterForProoflist"]);
    Route::middleware(["permission:perform_compensation_payroll_prooflist"])->get("/export-payroll-prooflist-converter", [PayrollProofListController::class, "exportProoflist"]);

    //entries
    Route::get("/get-all-entries", [EntryController::class, "getAllEntries"]);
    Route::middleware(["permission:perform_compensation_entries"])->post("/generate-entries", [EntryController::class, "generateEntries"]);
    Route::middleware(["permission:export_all_compensation_entries"])->get("/export-all-entries", [EntryController::class, "exportAllEntries"]);

});
