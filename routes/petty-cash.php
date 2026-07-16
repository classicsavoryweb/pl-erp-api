<?php

use App\Http\Controllers\PettyCash\DepartmentFundController;
use App\Http\Controllers\PettyCash\CashIssuanceController;
use App\Http\Controllers\PettyCash\LiquidationController;
use Illuminate\Support\Facades\Route;

// PETTY CASH MODULE
Route::middleware('auth:sanctum')->group(function () {
    // Department Fund
    Route::get('/get-my-custodian-info', [DepartmentFundController::class, 'getMyCustodianInfo']);
    Route::get('/get-petty-cash-transaction-types', [DepartmentFundController::class, 'getTransactionTypes']);
    Route::get('/get-department-fund-transactions', [DepartmentFundController::class, 'getTransactions']);
    Route::post('/add-department-fund-transaction', [DepartmentFundController::class, 'addTransaction']);

    // Cash Issuance
    Route::get('/get-all-cash-issuances', [CashIssuanceController::class, 'getAllCashIssuances']);
    Route::get('/export-all-cash-issuances', [CashIssuanceController::class, 'exportAllCashIssuances']);
    Route::get('/get-pcv-number', [CashIssuanceController::class, 'getPCVNumber']);
    Route::get('/get-branch-employees', [CashIssuanceController::class, 'getBranchEmployees']);
    Route::get('/get-my-employees', [CashIssuanceController::class, 'getMyEmployees']);
    Route::get('/get-approver-employees', [CashIssuanceController::class, 'getApproverEmployees']);
    Route::get('/get-branch-approvers', [CashIssuanceController::class, 'getBranchApprovers']);
    Route::get('/get-cash-issuance/{id}', [CashIssuanceController::class, 'getCashIssuance']);
    Route::post('/create-cash-issuance', [CashIssuanceController::class, 'createCashIssuance']);
    Route::post('/void-cash-issuance/{id}', [CashIssuanceController::class, 'voidCashIssuance']);

    // Liquidation
    Route::get('/get-liquidations/{issuanceId}', [LiquidationController::class, 'getLiquidations']);
    Route::get('/get-liquidation-attachment/{id}', [LiquidationController::class, 'getLiquidationAttachment']);
    Route::post('/create-liquidation', [LiquidationController::class, 'createLiquidation']);
    Route::post('/edit-liquidation/{id}', [LiquidationController::class, 'editLiquidation']);
    Route::post('/void-liquidation/{id}', [LiquidationController::class, 'voidLiquidation']);
    Route::post('/post-liquidation/{id}', [LiquidationController::class, 'postLiquidation']);
});
