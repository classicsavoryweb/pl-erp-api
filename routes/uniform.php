<?php

use App\Http\Controllers\Uniform\ItemMasterlistController;
use App\Http\Controllers\Uniform\RequestAndIssuanceController;
use App\Http\Controllers\Uniform\RequestAndIssuanceItemsController;
use App\Http\Controllers\Uniform\StockReplenishmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/get-all-uniform-items', [ItemMasterlistController::class, 'getAllItems']);
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/export-all-uniform-items', [ItemMasterlistController::class, 'exportAllItems']);
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/get-uniform-item-options', [ItemMasterlistController::class, 'getOptions']);
    Route::middleware(['permission:perform_uniform_item_masterlist'])->post('/create-uniform-item', [ItemMasterlistController::class, 'createItem']);
    Route::middleware(['permission:perform_uniform_item_masterlist'])->post('/edit-uniform-item/{id}', [ItemMasterlistController::class, 'editItem']);

    Route::get('/uniform-stock-options', [StockReplenishmentController::class, 'options'])
        ->middleware('permission:view_uniform_replenishment_request|view_uniform_receiving_stocks');

    Route::middleware(['permission:view_uniform_replenishment_request'])->group(function () {
        Route::get('/uniform-replenishments', [StockReplenishmentController::class, 'replenishments']);
    });

    Route::middleware(['permission:view_uniform_receiving_stocks'])->group(function () {
        Route::get('/uniform-receivable-replenishments', [StockReplenishmentController::class, 'receivableReplenishments']);
        Route::get('/uniform-receivings', [StockReplenishmentController::class, 'receivings']);
    });

    Route::middleware(['permission:perform_uniform_replenishment_request'])->group(function () {
        Route::post('/uniform-replenishments', [StockReplenishmentController::class, 'storeReplenishment']);
        Route::post('/uniform-replenishments/{id}/review', [StockReplenishmentController::class, 'reviewReplenishment']);
        Route::post('/uniform-replenishments/{id}/post', [StockReplenishmentController::class, 'postReplenishment']);
        Route::post('/uniform-replenishments/{id}/cancel', [StockReplenishmentController::class, 'cancelReplenishment']);
    });

    Route::middleware(['permission:perform_uniform_receiving_stocks'])->group(function () {
        Route::post('/uniform-receivings', [StockReplenishmentController::class, 'storeReceiving']);
        Route::post('/uniform-receivings/{id}/review', [StockReplenishmentController::class, 'reviewReceiving']);
        Route::post('/uniform-receivings/{id}/post', [StockReplenishmentController::class, 'postReceiving']);
        Route::post('/uniform-receivings/{id}/cancel', [StockReplenishmentController::class, 'cancelReceiving']);
    });
    Route::middleware(['permission:view_uniform_request_and_issuance'])->get("/get-uniform-request-and-issuance", [RequestAndIssuanceController::class, "getUniformRequestAndIssuance"]);
     Route::middleware(['permission:perform_uniform_request_and_issuance'])->post("/create-uniform-request-and-issuance", [RequestAndIssuanceController::class, "createUniformRequestAndIssuance"]);
    Route::middleware(['permission:perform_uniform_request_and_issuance'])->post("/create-uniform-request-and-issuance-items", [RequestAndIssuanceItemsController::class, "createUniformRequestAndIssuanceItems"]);
    Route::middleware(['permission:perform_uniform_request_and_issuance'])->post('/issuance/{id}/status', [RequestAndIssuanceController::class, 'updateStatus']);
    Route::get("/get-drop-down-masterlist", [RequestAndIssuanceItemsController::class, "getOptionsMasterList"]);
    Route::middleware(['permission:perform_uniform_request_and_issuance'])->post('/uniform-request/{id}/cancel', [RequestAndIssuanceController::class, 'cancelUniformRequestAndIssuance']);

    
});
 
