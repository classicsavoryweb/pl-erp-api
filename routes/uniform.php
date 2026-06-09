<?php

use App\Http\Controllers\Uniform\ItemMasterlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/get-all-uniform-items', [ItemMasterlistController::class, 'getAllItems']);
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/export-all-uniform-items', [ItemMasterlistController::class, 'exportAllItems']);
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/get-uniform-item-options', [ItemMasterlistController::class, 'getOptions']);
    Route::middleware(['permission:perform_uniform_item_masterlist'])->post('/create-uniform-item', [ItemMasterlistController::class, 'createItem']);
    Route::middleware(['permission:perform_uniform_item_masterlist'])->post('/edit-uniform-item/{id}', [ItemMasterlistController::class, 'editItem']);
});
