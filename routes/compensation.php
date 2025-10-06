<?php

use App\Http\Controllers\Compensation\CutOffController;
use App\Http\Controllers\Compensation\EmployeeDeductionsController;
use App\Http\Controllers\Compensation\DeductionHistoryController;
use App\Http\Controllers\Compensation\PayrollController;
use Illuminate\Support\Facades\Route;

//compensation routes
Route::middleware('auth:sanctum')->group( function () {

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
});
