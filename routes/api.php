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

    //roles and permission
    Route::get("/get-all-roles-and-permissions", [RolesPermissionController::class, "getAllRolesAndPermissions"]);
    Route::get("/get-all-permissions", [RolesPermissionController::class, "getAllPermissions"]);
    Route::middleware(["permission:can_create_roles_and_permissions"])->post("/update-or-create-roles-and-permissions", [RolesPermissionController::class, "updateOrCreateNewRole"]);
    Route::get("/get-all-roles", [RolesPermissionController::class,"getAllRoles"]);
    Route::middleware(["permission:can_create_roles_and_permissions"])->get("/delete-role/{id}", [RolesPermissionController::class,"deleteRolesAndPermissions"]);
    
    //users module
    Route::middleware(["permission:can_create_users"])->post('/create-user', [AuthController::class, 'createUser']);
    Route::get('/get-all-users', [UserController::class, "getAllUsers"]);
    Route::middleware(["permission:can_create_users"])->post("/edit-user/{id}", [UserController::class,"editUser"]);

    //maintenance module
    Route::get("/get-all-stations", [StationController::class, "getAllStation"]);
    Route::get("/get-all-status", [EmployeeStatusController::class, "getAllEmployeStatus"]);
});

