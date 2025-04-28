<?php

use App\Http\Controllers\Admin\RolesPermissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Maintenance\EmployeeStatusController;
use App\Http\Controllers\Maintenance\StationController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, "login"]);
Route::middleware('auth:sanctum')->group( function () {

    Route::get('/logout', [AuthController::class,'logout']);
    Route::post('/change-password', [AuthController::class,'changePassword']);


    // ADMINISTRATOR MODULE
    //roles and permission
    Route::get("/get-all-roles-and-permissions", [RolesPermissionController::class, "getAllRolesAndPermissions"]);
    Route::get("/get-all-permissions", [RolesPermissionController::class, "getAllPermissions"]);
    Route::middleware(["permission:can_create_roles_and_permissions"])->post("/update-or-create-roles-and-permissions", [RolesPermissionController::class, "updateOrCreateNewRole"]);
    Route::get("/get-all-roles", [RolesPermissionController::class,"getAllRoles"]);
    Route::middleware(["permission:can_create_roles_and_permissions"])->get("/delete-role/{id}", [RolesPermissionController::class,"deleteRolesAndPermissions"]);
    //users
    Route::middleware(["permission:can_create_users"])->post('/create-user', [AuthController::class, 'createUser']);
    Route::get('/get-all-users', [UserController::class, "getAllUsers"]);
    Route::middleware(["permission:can_create_users"])->post("/edit-user/{id}", [UserController::class,"editUser"]);

    // MAINTENANCE MODULE
    //stations
    Route::get("/get-all-stations", [StationController::class, "getAllStation"]);
    Route::middleware(["permission:can_create_stations"])->get("/delete-station/{id}", [StationController::class,"deleteStation"]);
    Route::middleware(["permission:can_create_stations"])->post("/edit-station/{id}", [StationController::class,"editStation"]);
    Route::middleware(["permission:can_create_stations"])->post("/create-station", [StationController::class,"createStation"]);
    //employee status
    Route::get("/get-all-status", [EmployeeStatusController::class, "getAllEmployeStatus"]);
    Route::middleware(["permission:can_create_employee_status"])->post("/create-employee-status", [EmployeeStatusController::class, "createEmployeeStatus"]);
    Route::middleware(["permission:can_create_employee_status"])->post("/edit-employee-status", [EmployeeStatusController::class, "editEmployeeStatus"]);
    Route::middleware(["permission:can_create_employee_status"])->get("/delete-employee-status/{id}", [EmployeeStatusController::class, "deleteEmployeeStatus"]);
});

