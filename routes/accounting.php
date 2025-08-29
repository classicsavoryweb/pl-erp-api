<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\VendorController;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->group(function() {

    //accounts
    Route::get("/get-accounts", [AccountController::class, "getAccounts"]);
    //vendors
    Route::get("/get-vendors", [VendorController::class, "getVendors"]);
});