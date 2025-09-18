<?php

use App\Http\Controllers\Compensation\CutOffController;
use App\Http\Controllers\Compensation\EmployeeDeductionsController;
use App\Http\Controllers\Compensation\DeductionHistoryController;
use Illuminate\Support\Facades\Route;

//compensation routes
Route::middleware('auth:sanctum')->group( function () {

    //cutoff
    Route::get("/get-all-cut-off",[CutOffController::class, "getAllCutOffs"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/create-cut-off", [CutOffController::class, "createCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/edit-cut-off/{id}", [CutOffController::class, "editCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->get("/initialize-cut-off/{id}", [CutOffController::class, "initializeCutOff"]);
    
    //employee deductions
    Route::get("/get-all-employee-deductions", [EmployeeDeductionsController::class, "getAllEmployeeDeductions"]);
    Route::get("/get-employee-deductions", [EmployeeDeductionsController::class, "getEmployeeDeductions"]);
    Route::get("/export-all-employee-deductions", [EmployeeDeductionsController::class, "exportAllEmployeeDeductions"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/create-employee-deduction", [EmployeeDeductionsController::class, "createEmployeeDeduction"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/edit-employee-deduction/{id}", [EmployeeDeductionsController::class, "editEmployeeDeduction"]);
    Route::middleware(["permission:perform_compensation_deductions"])->post("/end-loan-transaction/{id}", [EmployeeDeductionsController::class, "endLoanTransaction"]);
});
