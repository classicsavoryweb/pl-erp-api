<?php

use App\Http\Controllers\Uniform\ItemMasterlistController;
use App\Http\Controllers\Uniform\PhysicalCountController;
use App\Http\Controllers\Uniform\RequestAndIssuanceController;
use App\Http\Controllers\Uniform\RequestAndIssuanceItemsController;
use App\Http\Controllers\Uniform\StockReplenishmentController;
use App\Http\Controllers\Uniform\VarianceReportController;
use App\Http\Controllers\Uniform\WeightedAverageController;
use App\Http\Controllers\Uniform\WeightedAverageItemsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/get-all-uniform-items', [ItemMasterlistController::class, 'getAllItems']);
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/export-all-uniform-items', [ItemMasterlistController::class, 'exportAllItems']);
    Route::middleware(['permission:view_uniform_item_masterlist'])->get('/get-uniform-item-options', [ItemMasterlistController::class, 'getOptions']);
    Route::middleware(['permission:perform_uniform_item_masterlist'])->post('/create-uniform-item', [ItemMasterlistController::class, 'createItem']);
    Route::middleware(['permission:perform_uniform_item_masterlist'])->post('/edit-uniform-item/{id}', [ItemMasterlistController::class, 'editItem']);

    Route::middleware(['permission:view_uniform_replenishment_request|view_uniform_receiving_stocks'])->get('/uniform-stock-options', [StockReplenishmentController::class, 'options']);

    Route::middleware(['permission:view_uniform_replenishment_request'])->group(function () {
        Route::get('/uniform-replenishments', [StockReplenishmentController::class, 'replenishments']);
        Route::get('/export-uniform-replenishments/{id}/details', [StockReplenishmentController::class, 'exportReplenishmentDetails']);
    });

    Route::middleware(['permission:view_uniform_receiving_stocks'])->group(function () {
        Route::get('/uniform-receivable-replenishments', [StockReplenishmentController::class, 'receivableReplenishments']);
        Route::get('/uniform-receivings', [StockReplenishmentController::class, 'receivings']);
        Route::get('/export-uniform-receivings/{id}/details', [StockReplenishmentController::class, 'exportReceivingDetails']);
    });

    Route::middleware(['permission:perform_uniform_replenishment_request'])->group(function () {
        Route::post('/uniform-replenishments/generate-list', [StockReplenishmentController::class, 'generateReplenishmentList']);
        Route::post('/uniform-replenishments/import-items', [StockReplenishmentController::class, 'importReplenishmentItems']);
        Route::post('/uniform-replenishments', [StockReplenishmentController::class, 'storeReplenishment']);
        Route::post('/uniform-replenishments/{id}/review', [StockReplenishmentController::class, 'reviewReplenishment']);
        Route::post('/uniform-replenishments/{id}/post', [StockReplenishmentController::class, 'postReplenishment']);
        Route::post('/uniform-replenishments/{id}/cancel', [StockReplenishmentController::class, 'cancelReplenishment']);
        Route::post('/uniform-replenishments/{id}/replace-supplier', [StockReplenishmentController::class, 'replaceReplenishmentSupplier']);
        Route::post('/uniform-replenishments/{id}/close', [StockReplenishmentController::class, 'closeReplenishment']);
    });

    Route::middleware(['permission:perform_uniform_receiving_stocks'])->group(function () {
        Route::post('/uniform-receivings', [StockReplenishmentController::class, 'storeReceiving']);
        Route::post('/uniform-receivings/{id}/review', [StockReplenishmentController::class, 'reviewReceiving']);
        Route::post('/uniform-receivings/{id}/post', [StockReplenishmentController::class, 'postReceiving']);
        Route::post('/uniform-receivings/{id}/cancel', [StockReplenishmentController::class, 'cancelReceiving']);
    });

    Route::middleware(['permission:view_uniform_physical_count'])->get('/uniform-physical-counts', [PhysicalCountController::class, 'physicalCounts']);
    Route::middleware(['permission:view_uniform_physical_count'])->get('/export-uniform-physical-counts', [PhysicalCountController::class, 'exportPhysicalCounts']);
    Route::middleware(['permission:view_uniform_physical_count'])->get('/export-uniform-physical-counts/{id}/details', [PhysicalCountController::class, 'exportPhysicalCountDetails']);
    Route::middleware(['permission:perform_uniform_physical_count'])->post('/uniform-physical-counts/preview', [PhysicalCountController::class, 'previewPhysicalCount']);
    Route::middleware(['permission:perform_uniform_physical_count'])->post('/uniform-physical-counts', [PhysicalCountController::class, 'storePhysicalCount']);
    Route::middleware(['permission:perform_uniform_physical_count'])->post('/uniform-physical-counts/{id}/review', [PhysicalCountController::class, 'reviewPhysicalCount']);
    Route::middleware(['permission:perform_uniform_physical_count'])->post('/uniform-physical-counts/{id}/post', [PhysicalCountController::class, 'postPhysicalCount']);
    Route::middleware(['permission:perform_uniform_physical_count'])->post('/uniform-physical-counts/{id}/cancel', [PhysicalCountController::class, 'cancelPhysicalCount']);
    Route::middleware(['permission:perform_uniform_physical_count'])->post('/update-quantity', [PhysicalCountController::class, 'updateQuantity']); 
    Route::middleware(['permission:view_uniform_variance_report'])->get('/physical_count_dropdown', [PhysicalCountController::class, 'dropdownPhysicalCounts']);

    Route::middleware(['permission:view_uniform_variance_report'])->get('/uniform-variances', [VarianceReportController::class, 'variances']);
    Route::middleware(['permission:view_uniform_variance_report'])->get('/export-uniform-variances', [VarianceReportController::class, 'exportVariances']);
    Route::middleware(['permission:view_uniform_variance_report'])->get('/export-uniform-variances/{id}/details', [VarianceReportController::class, 'exportVarianceDetails']);
    Route::middleware(['permission:view_uniform_variance_report'])->get('/uniform-variances/{id}', [VarianceReportController::class, 'getVarianceItemById']);
    Route::middleware(['permission:view_uniform_variance_report'])->get('/variance-details', [VarianceReportController::class, 'getVarianceItemDetails']);
    
    Route::middleware(['permission:perform_uniform_variance_report'])->post('/uniform-variances/preview', [VarianceReportController::class, 'previewVariance']);
    Route::middleware(['permission:perform_uniform_variance_report'])->post('/uniform-variances', [VarianceReportController::class, 'storeVariance']);
    Route::middleware(['permission:perform_uniform_variance_report'])->post('/uniform-variances/{id}/review', [VarianceReportController::class, 'reviewVariance']);
    Route::middleware(['permission:perform_uniform_variance_report'])->post('/uniform-variances/{id}/post', [VarianceReportController::class, 'postVariance']);
    Route::middleware(['permission:perform_uniform_variance_report'])->post('/uniform-variances/{id}/cancel', [VarianceReportController::class, 'cancelVariance']);

    Route::middleware(['permission:view_uniform_request_and_issuance'])->get('/get-uniform-request-and-issuance', [RequestAndIssuanceController::class, 'getUniformRequestAndIssuance']);
    Route::middleware(['permission:view_uniform_request_and_issuance'])->get('/export-uniform-request-and-issuance/{id}/details', [RequestAndIssuanceController::class, 'exportRequestAndIssuanceDetails']);
    Route::middleware(['permission:perform_uniform_request_and_issuance'])->post('/create-uniform-request-and-issuance', [RequestAndIssuanceController::class, 'createUniformRequestAndIssuance']);
    Route::middleware(['permission:perform_uniform_request_and_issuance'])->post('/uniform-request/{id}/review', [RequestAndIssuanceController::class, 'reviewUniformRequestAndIssuance']);
    Route::middleware(['permission:perform_uniform_request_and_issuance'])->post('/issuance/{id}/status', [RequestAndIssuanceController::class, 'updateStatus']);
    Route::middleware(['permission:view_uniform_request_and_issuance'])->get('export-all-request-and-issuance', [RequestAndIssuanceController::class, 'exportRequestandIssuance']);
    Route::get('/get-drop-down-masterlist', [RequestAndIssuanceItemsController::class, 'getOptionsMasterList']);
    Route::middleware(['permission:perform_uniform_request_and_issuance'])->post('/uniform-request/{id}/cancel', [RequestAndIssuanceController::class, 'cancelUniformRequestAndIssuance']);
    Route::middleware(['permission:view_uniform_weighted_average'])->get('/get-weight-average', [WeightedAverageController::class, 'getWeightAverage']);
    Route::middleware(['permission:view_uniform_weighted_average'])->get('/get-weight-average-export', [WeightedAverageController::class, 'exportAllWeightAverage']);
    Route::middleware(['permission:view_uniform_weighted_average'])->get('/export-weight-average/{id}/details', [WeightedAverageController::class, 'exportWeightAverageDetails']);
    Route::middleware(['permission:perform_uniform_weighted_average'])->post('/uniform-weighted/{id}/cancel',[WeightedAverageController::class, 'cancelWeightedAverage']);
    Route::middleware(['permission:perform_uniform_weighted_average'])->post('/create-weight-average',[WeightedAverageController::class,'createWeightedAverage']);
    Route::middleware(['permission:perform_uniform_weighted_average'])->post('/weight-average-items', [WeightedAverageController::class, 'getReceivingItems']);
    Route::middleware(['permission:view_uniform_weighted_average'])->get('/weighted-average/{id}', [WeightedAverageItemsController::class, 'getWeightAverageById']);       
    Route::middleware(['permission:perform_uniform_weighted_average'])->post('/weighted/{id}/status', [WeightedAverageController::class, 'updateWeightedStatus']);
    Route::middleware(['permission:perform_uniform_weighted_average'])->post('/update-item-price' , [WeightedAverageController::class,'updatePrice']);

});
