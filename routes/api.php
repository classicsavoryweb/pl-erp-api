<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, "login"]);
Route::middleware('auth:sanctum')->group( function () {

    Route::get('/logout', [AuthController::class,'logout']);
    Route::post('/change-password', [AuthController::class,'changePassword']);

    Route::middleware(["permission:can_create_users"])->post('/create-user', [AuthController::class, 'createUser']);
});
