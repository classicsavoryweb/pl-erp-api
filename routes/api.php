<?php

use App\Http\Controllers\Admin\RolesPermissionController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, "login"]);
Route::middleware('auth:sanctum')->group( function () {

    Route::get('/logout', [AuthController::class,'logout']);
    Route::post('/change-password', [AuthController::class,'changePassword']);

    //roles and permission
    Route::middleware(["permission:can_view_roles_and_permissions"])->get("/get-all-roles-and-permissions", [RolesPermissionController::class, "getAllRolesAndPermissions"]);

    //users module
    Route::middleware(["permission:can_create_users"])->post('/create-user', [AuthController::class, 'createUser']);
});
