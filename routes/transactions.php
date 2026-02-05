<?php

use App\Http\Controllers\Transactions\ActualTimeController;
use App\Http\Controllers\Transactions\AdjustmentController;
use App\Http\Controllers\Transactions\OvertimeController;
use App\Http\Controllers\Transactions\ScheduleTimeController;
use App\Http\Controllers\Transactions\TimeKeepingController;
use Illuminate\Support\Facades\Route;

//TRANSACTIONS MODULE
Route::middleware('auth:sanctum')->group(function() {
    //schedule time
    Route::get("/get-all-transactions-schedule-time", [ScheduleTimeController::class, "getAllTransactionsScheduleTime"]);
    Route::get("/get-transactions-schedule-time", [ScheduleTimeController::class, "getTransactionsScheduleTime"]);
    Route::middleware(["permission:import_all_transactions_schedule_time"])->post("/validate-import-transactions-schedule-time", [ScheduleTimeController::class, "validateImportTransactionsScheduleTimeExcel"]);
    Route::middleware(["permission:import_all_transactions_schedule_time"])->post("/import-transactions-schedule-time", [ScheduleTimeController::class, "importTransactionsScheduleTimeExcel"]);
    Route::middleware(["permission:export_all_transactions_schedule_time"])->get("/export-all-transactions-schedule-time", [ScheduleTimeController::class, "exportAllTransactionsScheduleTime"]);
    Route::middleware(["permission:import_all_transactions_schedule_time"])->post("/fetch-transactions-schedule-time", [ScheduleTimeController::class, "fetchSchedules"]);
    Route::middleware(["permission:transfer_time_keeping_branch"])->post("/transfer-timekeeping-branch/{schedule_id}", [ScheduleTimeController::class, "transferTimekeepingBranch"]);

    //actual logs
    Route::get("/get-all-transactions-actual-time", [ActualTimeController::class, "getAllBiotimeTransactions"]);
    Route::middleware(["permission:perform_transactions_actual_time"])->post("/edit-biotime-punch/{id}", [ActualTimeController::class, "editBiotimePunch"]);
    Route::middleware(["permission:perform_transactions_actual_time"])->post("/create-biotime-punch", [ActualTimeController::class, "createBiotimeLog"]);
    Route::middleware(["permission:perform_transactions_actual_time"])->get("/get-biotime-punch-deletion-logs/{id}", [ActualTimeController::class, "getBiotimePunchDeletionLogs"]);
    Route::middleware(["permission:import_transactions_actual_time"])->post("/import-biotime-logs", [ActualTimeController::class, "importTransactionsActualTimeLogs"]);
    Route::middleware(["permission:export_all_transactions_actual_logs"])->get("/export-all-transactions-actual-logs", [ActualTimeController::class, "exportAllTransactionsActualLogs"]);
    Route::middleware(["permission:perform_transactions_actual_time"])->post("/fetch-biotime-logs", [ActualTimeController::class, "fetchBiotimeLogs"]);

    //overtime
    Route::get("/get-all-overtime", [OvertimeController::class, "getAllOvertime"]);
    Route::get("/get-overtime", [OvertimeController::class, "getOvertime"]);
    Route::middleware(["permission:perform_transactions_overtime"])->post("/create-overtime", [OvertimeController::class, "createOvertime"]);
    Route::middleware(["permission:perform_transactions_overtime"])->post("/edit-overtime/{id}", [OvertimeController::class, "editOvertime"]);
    Route::middleware(["permission:import_all_transactions_overtime"])->post("/import-overtime", [OvertimeController::class, "importOvertimeExcel"]);
    Route::middleware(["permission:export_all_transactions_overtime"])->get("/export-all-overtime", [OvertimeController::class, "exportAllOvertime"]);
    Route::middleware(["permission:approve_all_transactions_overtime"])->get("/approve-overtime/{id}", [OvertimeController::class, "approveOvertime"]);
    Route::middleware(["permission:void_all_transactions_overtime"])->get("/void-overtime/{id}", [OvertimeController::class, "voidOvertime"]);
    Route::middleware(["permission:approve_all_transactions_overtime"])->post("/approve-all-overtime", [OvertimeController::class, "approveBulkOvertime"]);
    Route::middleware(["permission:unapprove_all_transactions_overtime"])->get("/unapprove-overtime/{id}", [OvertimeController::class, "unapproveOvertime"]);
    Route::middleware(["permission:force_adjust_all_transactions_overtime"])->post("/force-adjust-overtime/{id}", [OvertimeController::class, "forceAdjustOvertime"]);
    Route::middleware(["permission:force_adjust_all_transactions_overtime"])->post("/force-adjust-overtime-without-timekeeping", [AdjustmentController::class, "adjustTimeKeepingWithOtherOvertime"]);
    Route::middleware(["permission:import_all_transactions_overtime"])->get("/fetch-overtime", [OvertimeController::class, "fetchOvertime"]);


    //time keeping
    Route::middleware(["permission:perform_transactions_time_keeping"])->post("/generate-time-keeping", [TimeKeepingController::class, "generateTimeKeeping"]);
    Route::get("/get-all-time-keeping", [TimeKeepingController::class, "getAllTimeKeeping"]);
    Route::middleware(["permission:export_all_transactions_time_keeping"])->get("/export-all-time-keeping", [TimeKeepingController::class, "exportAllTimeKeeping"]);
    Route::get("/get-time-keeping", [TimeKeepingController::class, "getTimeKeeping"]);
    Route::middleware(["permission:perform_transactions_time_keeping"])->post("/update-time-keeping-undertime", [TimeKeepingController::class, "updateTimeKeepingUndertime"]);
    Route::get("/trigger-fetch-biotime", [TimeKeepingController::class, "triggerFetchBiotimeLogs"]);
    
    //time keeping summary
    Route::middleware(["permission:perform_transactions_time_keeping_summary"])->post("/generate-time-keeping-summary", [TimeKeepingController::class, "generateTimeKeepingSummary"]);
    Route::get("/get-all-time-keeping-summary", [TimeKeepingController::class, "getAllTimeKeepingSummary"]);
    Route::middleware(["permission:export_all_transactions_time_keeping_summary"])->get("/export-all-time-keeping-summary", [TimeKeepingController::class, "exportAllTimeKeepingSummary"]);
    
    //adjustments
    Route::get("/get-all-adjustments", [AdjustmentController::class, "getAllAdjustments"]);
    Route::get("/get-adjustment/{id}", [AdjustmentController::class, "getAdjustment"]);
    Route::middleware("permission:approve_transactions_adjustments")->get("/approve-adjustments/{id}", [AdjustmentController::class, "approveAdjustments"]);
    Route::middleware("permission:unapprove_transactions_adjustments")->get("/unapprove-adjustments/{id}", [AdjustmentController::class, "unapproveAdjustments"]);
    Route::middleware("permission:unapprove_transactions_adjustments")->get("/void-adjustments/{id}", [AdjustmentController::class, "voidAdjustments"]);
    Route::middleware("permission:perform_transactions_adjustments")->post("/create-adjustments", [AdjustmentController::class, "createAdjustments"]);
    // Route::middleware("permission:perform_transactions_adjustments")->post("/edit-adjustments/{id}", [AdjustmentController::class, "editAdjustments"]);

});