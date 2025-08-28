<?php

use App\Http\Controllers\Reports\ReportCoeRequestsController;
use App\Http\Controllers\Reports\ReportsEmploymentStatusController;
use App\Http\Controllers\Reports\ReportsHealthSafetyController;
use App\Http\Controllers\Reports\ReportsOffensesController;
use App\Http\Controllers\Reports\ReportsTardinessController;
use App\Http\Controllers\Reports\ReportsVaccinationController;
use Illuminate\Support\Facades\Route;

//ALL RECORDS MODULE
Route::middleware('auth:sanctum')->group(function() {
    //employee offenses
    Route::get("/get-all-reports-offenses", [ReportsOffensesController::class, "getAllEmployeeOffenses"]);
    Route::middleware(["permission:export_all_records_offenses"])->get("/export-all-reports-offenses", [ReportsOffensesController::class, "exportAllEmployeeOffenses"]);
    //vaccination
    Route::get("/get-all-reports-vaccination", [ReportsVaccinationController::class, "getAllReportsVaccines"]);
    //health and safety
    Route::get("/get-all-reports-health-and-safety", [ReportsHealthSafetyController::class, "getAllReportsHealthAndSafety"]);
    //coe requests
    Route::get("/get-all-reports-coe-requests", [ReportCoeRequestsController::class, "getAllCoeRequests"]);
    Route::get("/get-reports-coe-request/{id}", [ReportCoeRequestsController::class, "getCoeRequest"]);
    Route::middleware(["permission:perform_all_records_coe_requests"])->post("/add-reports-coe-request", [ReportCoeRequestsController::class, "createCoeRequest"]);
    Route::middleware(["permission:perform_all_records_coe_requests"])->post("/edit-reports-coe-request/{id}", [ReportCoeRequestsController::class, "editCoeRequest"]);
    Route::middleware(["permission:cancel_all_records_coe_requests"])->get("/cancel-reports-coe-request/{id}", [ReportCoeRequestsController::class, "cancelCoeRequest"]);
    Route::middleware(["permission:approve_all_records_coe_requests"])->get("/approve-reports-coe-request/{id}", [ReportCoeRequestsController::class, "approveCoeRequest"]);
    //tardiness
    Route::get("/get-all-reports-tardiness", [ReportsTardinessController::class, "getAllReportsTardiness"]);
    Route::get("/export-reports-tardiness", [ReportsTardinessController::class, "exportTardiness"]);
    Route::middleware(["permission:perform_all_records_tardiness"])->post("/void-reports-tardiness/{id}", [ReportsTardinessController::class, "voidTardiness"]);
    Route::middleware(["permission:approve_all_records_tardiness"])->get("/approve-reports-tardiness/{id}", [ReportsTardinessController::class, "approveTardiness"]);
    Route::middleware(["permission:import_all_records_tardiness"])->post("/import-reports-tardiness", [ReportsTardinessController::class, "importTardinessExcel"]);
    Route::get("/get-tardiness-details/{id}", [ReportsTardinessController::class, "getTardinessDetails"]);
    Route::middleware(["permission:approve_all_records_tardiness"])->post("/approve-reports-all-tardiness", [ReportsTardinessController::class, "approveBulkTardiness"]);
    Route::middleware(["permission:perform_all_records_tardiness"])->post("/edit-reports-minutes-tardiness", [ReportsTardinessController::class, "editMinutesOfTardiness"]);
    Route::middleware(["permission:perform_all_records_tardiness"])->post("/send-report-tardiness-nte/{id}", [ReportsTardinessController::class, "sendNTE"]);
    Route::middleware(["permission:perform_all_records_tardiness"])->post("/send-report-tardiness-nod/{id}", [ReportsTardinessController::class, "sendNOD"]);
    Route::get("/get-reports-nte-details/{id}", [ReportsTardinessController::class, "getNTEDetails"]);
    //employment status
    Route::middleware(["permission:perform_all_records_employment_status"])->get("/get-employment-status-report", [ReportsEmploymentStatusController::class, "getEmploymentStatusReports"]);

});