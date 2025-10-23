<?php

use App\Http\Controllers\Reports\ReportsPagibigController;
use Illuminate\Support\Facades\Route;

// REPORTS MODULE
Route::middleware('auth:sanctum')->group(function() {
    //pagibig
    Route::get("/get-all-reports-pagibig-contributions", [ReportsPagibigController::class, "getAllReportsPagibigContributions"]);
    Route::get("/get-all-reports-pagibig-loans", [ReportsPagibigController::class, "getAllReportsPagibigLoans"]);
    Route::get("/export-all-reports-pagibig-contributions", [ReportsPagibigController::class, "exportAllReportsPagibigContributions"]);
    Route::get("/export-all-reports-pagibig-loans", [ReportsPagibigController::class, "exportAllReportsPagibigLoans"]);
});