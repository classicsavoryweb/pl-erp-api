<?php

use App\Http\Controllers\Admin\ActivityLogsController;
use App\Http\Controllers\Admin\DatabaseBackupsController;
use App\Http\Controllers\Admin\RolesPermissionController;
use App\Http\Controllers\Admin\ScheduleTimekeepingController;
use App\Http\Controllers\Admin\TardinessDisablerController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// ADMINISTRATOR MODULE
Route::middleware('auth:sanctum')->group(function() {
    //roles and permission
    Route::get("/get-all-activity-logs", [ActivityLogsController::class, "getAllActivityLogs"]);
    Route::get("/get-all-roles-and-permissions", [RolesPermissionController::class, "getAllRolesAndPermissions"]);
    Route::get("/get-all-permissions", [RolesPermissionController::class, "getAllPermissions"]);
    Route::get("/get-all-roles", [RolesPermissionController::class,"getAllRoles"]);
    Route::middleware(["permission:perform_roles_and_permissions"])->post("/update-or-create-roles-and-permissions", [RolesPermissionController::class, "updateOrCreateNewRole"]);
    Route::middleware(["permission:perform_roles_and_permissions"])->get("/delete-role/{id}", [RolesPermissionController::class,"deleteRolesAndPermissions"]);
    //users
    Route::get('/get-all-users', [UserController::class, "getAllUsers"]);
    Route::middleware(["permission:perform_users"])->post('/create-user', [AuthController::class, 'createUser']);
    Route::middleware(["permission:perform_users"])->post("/edit-user/{id}", [UserController::class,"editUser"]);
    Route::get("/export-admin-users", [UserController::class,"exportUsers"]);
    //database backup
    Route::get("/get-all-database-backups", [DatabaseBackupsController::class, "getAllDatabaseBackups"]);
    Route::middleware(["permission:perform_database_backups"])->post("/create-database-backup", [DatabaseBackupsController::class,"createDatabaseBackup"]);
    Route::middleware(["permission:perform_database_backups"])->get('/download-sql-file/{filename}', [DatabaseBackupsController::class, 'downloadSqlFile']);
    //tardiness disabler
    Route::middleware(["permission:view_tardiness_disabler"])->get("/get-all-tardiness-disablers", [TardinessDisablerController::class, "getAllTardinessDisablers"]);
    Route::middleware(["permission:view_tardiness_disabler"])->post("/upsert-tardiness-disabler", [TardinessDisablerController::class, "upsertTardinessDisabler"]);

    Route::get("/get-specific-activity-logs", [ActivityLogsController::class, "getSpecificActivityLogs"]);

    //scheduled timekeeping generation
    Route::middleware(["permission:view_scheduled_timekeeping"])->get("/get-scheduled-timekeeping-generation", [ScheduleTimekeepingController::class, "getAllScheduledTimeKeeping"]);
    Route::middleware(["permission:perform_scheduled_timekeeping"])->post("/create-scheduled-timekeeping-generation", [ScheduleTimekeepingController::class, "createScheduleTimeKeepingGeneration"]);
});