<?php

use App\Http\Controllers\Reports\ReportsPagibigController;
use App\Http\Controllers\Reports\ReportsPhilhealthController;
use App\Http\Controllers\Reports\ReportsSSSController;
use Illuminate\Support\Facades\Route;

// REPORTS MODULE
Route::middleware('auth:sanctum')->group(function() {
    //pagibig
    Route::get("/get-all-reports-pagibig-contributions", [ReportsPagibigController::class, "getAllReportsPagibigContributions"]);
    Route::get("/get-all-reports-pagibig-loans", [ReportsPagibigController::class, "getAllReportsPagibigLoans"]);
    Route::get("/export-all-reports-pagibig-contributions", [ReportsPagibigController::class, "exportAllReportsPagibigContributions"]);
    Route::get("/export-all-reports-pagibig-loans", [ReportsPagibigController::class, "exportAllReportsPagibigLoans"]);

    //philhealth
    Route::get("/get-all-reports-philhealth-contributions", [ReportsPhilhealthController::class, "getAllReportsPhilhealthContributions"]);
    Route::get("/export-all-reports-philhealth-contributions", [ReportsPhilhealthController::class, "exportAllReportsPhilhealthContributions"]);

    //sss
    Route::get("/get-all-reports-sss-contributions", [ReportsSSSController::class, "getAllReportsSSSContributions"]);
    Route::get("/get-all-reports-sss-loans", [ReportsSSSController::class, "getAllReportsSSSLoans"]);
    Route::get("/export-all-reports-sss-contributions", [ReportsSSSController::class, "exportAllReportsSSSContributions"]);
    Route::get("/export-all-reports-sss-loans", [ReportsSSSController::class, "exportAllReportsSSSLoans"]);
});