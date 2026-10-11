<?php

use App\Http\Controllers\PettyCash\DepartmentFundController;
use App\Http\Controllers\PettyCash\CashIssuanceController;
use App\Http\Controllers\PettyCash\LiquidationController;
use App\Http\Controllers\PettyCash\ReplenishmentController;
use App\Http\Controllers\PettyCash\PettyCashAuditController;
use Illuminate\Support\Facades\Route;

// PETTY CASH MODULE
Route::middleware('auth:sanctum')->group(function () {
    // Department Fund
    Route::get('/get-my-custodian-info', [DepartmentFundController::class, 'getMyCustodianInfo']);
    Route::get('/get-petty-cash-transaction-types', [DepartmentFundController::class, 'getTransactionTypes']);
    Route::get('/get-posted-replenishments', [DepartmentFundController::class, 'getPostedReplenishments']);
    Route::get('/get-department-fund-transactions', [DepartmentFundController::class, 'getTransactions']);
    Route::post('/add-department-fund-transaction', [DepartmentFundController::class, 'addTransaction']);

    // Cash Issuance
    Route::get('/get-all-cash-issuances', [CashIssuanceController::class, 'getAllCashIssuances']);
    Route::get('/export-all-cash-issuances', [CashIssuanceController::class, 'exportAllCashIssuances']);
    Route::get('/get-pcv-number', [CashIssuanceController::class, 'getPCVNumber']);
    Route::get('/get-petty-cash-branch-employees', [CashIssuanceController::class, 'getBranchEmployees']);
    Route::get('/get-my-employees', [CashIssuanceController::class, 'getMyEmployees']);
    Route::get('/get-approver-employees', [CashIssuanceController::class, 'getApproverEmployees']);
    Route::get('/get-branch-approvers', [CashIssuanceController::class, 'getBranchApprovers']);
    Route::get('/get-cash-issuance/{id}', [CashIssuanceController::class, 'getCashIssuance']);
    Route::post('/create-cash-issuance', [CashIssuanceController::class, 'createCashIssuance']);
    Route::post('/void-cash-issuance/{id}', [CashIssuanceController::class, 'voidCashIssuance']);
    Route::get('/get-cshub-import-candidates', [CashIssuanceController::class, 'getCSHubImportCandidates']);
    Route::post('/import-cshub-petty-cash', [CashIssuanceController::class, 'importCSHubPettyCash']);

    // Liquidation
    Route::get('/get-liquidations/{issuanceId}', [LiquidationController::class, 'getLiquidations']);
    Route::get('/get-liquidations-for-replenishment/{replenishmentId}', [LiquidationController::class, 'getLiquidationsForReplenishment'])
        ->middleware('permission:view_pettycash_audit_module');
    Route::get('/get-liquidation-attachment/{id}', [LiquidationController::class, 'getLiquidationAttachment']);
    Route::post('/create-liquidation', [LiquidationController::class, 'createLiquidation']);
    Route::post('/edit-liquidation/{id}', [LiquidationController::class, 'editLiquidation']);
    Route::post('/void-liquidation/{id}', [LiquidationController::class, 'voidLiquidation']);
    Route::post('/post-liquidation/{id}', [LiquidationController::class, 'postLiquidation']);
    Route::get('/get-cshub-liquidation-candidates/{issuanceId}', [LiquidationController::class, 'getCSHubLiquidationCandidates']);
    Route::post('/import-cshub-liquidations/{issuanceId}', [LiquidationController::class, 'importCSHubLiquidations']);
    Route::post('/audit-create-liquidation', [LiquidationController::class, 'auditCreateLiquidation'])
        ->middleware('permission:perform_pettycash_audit_module');
    Route::post('/audit-edit-liquidation/{id}', [LiquidationController::class, 'auditEditLiquidation'])
        ->middleware('permission:perform_pettycash_audit_module');
    Route::get('/get-liquidation-audit-logs/{replenishmentId}', [LiquidationController::class, 'getLiquidationAuditLogs'])
        ->middleware('permission:view_pettycash_audit_module');

    // Replenishment
    Route::get('/get-all-replenishments', [ReplenishmentController::class, 'getAllReplenishments']);
    Route::get('/export-all-replenishments', [ReplenishmentController::class, 'exportAllReplenishments']);
    Route::get('/get-pending-cash-issuances-for-replenishment', [ReplenishmentController::class, 'getPendingCashIssuances']);
    Route::get('/check-existing-replenishment', [ReplenishmentController::class, 'checkExistingReplenishment']);
    Route::get('/get-replenishment/{id}', [ReplenishmentController::class, 'getReplenishment']);
    Route::post('/create-replenishment', [ReplenishmentController::class, 'createReplenishment']);
    Route::post('/post-replenishment/{id}', [ReplenishmentController::class, 'postReplenishment']);
    Route::post('/void-replenishment/{id}', [ReplenishmentController::class, 'voidReplenishment']);

    // Petty Cash Audit
    Route::get('/get-all-replenishments-audit', [PettyCashAuditController::class, 'getAllReplenishmentsForAudit'])
        ->middleware('permission:view_pettycash_audit_module');
    Route::get('/export-all-replenishments-audit', [PettyCashAuditController::class, 'exportAllReplenishmentsForAudit'])
        ->middleware('permission:view_pettycash_audit_module');
    Route::post('/review-replenishment-audit/{id}', [PettyCashAuditController::class, 'reviewReplenishment'])
        ->middleware('permission:perform_pettycash_audit_module');
    Route::post('/post-replenishment-audit/{id}', [PettyCashAuditController::class, 'postAuditReplenishment'])
        ->middleware('permission:perform_pettycash_audit_module');
});
