<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, "login"]);
Route::middleware('auth:sanctum')->group( function () {
    Route::get('/logout', [AuthController::class,'logout']);
    Route::post('/change-password', [AuthController::class,'changePassword']);
    Route::get("/get-summary-details", [DashboardController::class, "getSummaryDetails"]);
    Route::get("/get-employees-birthmonth", [DashboardController::class, "getAllEmployeeBirthMonth"]);
    Route::get("/get-employees-expiring-contracts", [DashboardController::class, "getAllEmployeeExpiringContracts"]);
    Route::get('/notifications', [NotificationsController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationsController::class, 'unreadCount']);
    Route::post('/notifications/mark-read', [NotificationsController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [NotificationsController::class, 'markAllRead']);

    Route::get("/get-payroll-summary-details", [DashboardController::class, "getPayrollSummaryDetails"]);
});
