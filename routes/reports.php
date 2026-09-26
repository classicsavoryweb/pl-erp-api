<?php

use App\Http\Controllers\Compensation\PayslipController;
use App\Http\Controllers\Reports\BankAccountController;
use App\Http\Controllers\Reports\Bir1601CController;
use App\Http\Controllers\Reports\Bir2316Controller;
use App\Http\Controllers\Reports\IncentiveReportController;
use App\Http\Controllers\Reports\ReportsThirteenthMonthController;
use App\Http\Controllers\Reports\ReportsPagibigController;
use App\Http\Controllers\Reports\ReportsPhilhealthController;
use App\Http\Controllers\Reports\ReportsSSSController;
use App\Http\Controllers\Reports\ReportsBDOCashCardController;
use App\Http\Controllers\Reports\ReportsDeductionController;
use App\Http\Controllers\Reports\ReportsIncentiveAttendanceController;
use App\Http\Controllers\Reports\ReportsPayrollStatutoryController;
use App\Http\Controllers\Reports\TimeKeepingSummaryReportController;
use App\Http\Controllers\TimekeepingProoflistReportController;
use Illuminate\Support\Facades\Route;

// REPORTS MODULE
Route::middleware('auth:sanctum')->group(function() {
    //pagibig
    Route::get("/get-all-reports-pagibig-contributions", [ReportsPagibigController::class, "getAllReportsPagibigContributions"]);
    Route::get("/get-all-reports-pagibig-loans", [ReportsPagibigController::class, "getAllReportsPagibigLoans"]);
    Route::get("/get-reports-pagibig-loan-types", [ReportsPagibigController::class, "getReportsPagibigLoanTypes"]);
    Route::get("/export-all-reports-pagibig-contributions", [ReportsPagibigController::class, "exportAllReportsPagibigContributions"]);
    Route::get("/export-all-reports-pagibig-loans", [ReportsPagibigController::class, "exportAllReportsPagibigLoans"]);

    //philhealth
    Route::get("/get-all-reports-philhealth-contributions", [ReportsPhilhealthController::class, "getAllReportsPhilhealthContributions"]);
    Route::get("/export-all-reports-philhealth-contributions", [ReportsPhilhealthController::class, "exportAllReportsPhilhealthContributions"]);

    //sss
    Route::get("/get-all-reports-sss-contributions", [ReportsSSSController::class, "getAllReportsSSSContributions"]);
    Route::get("/get-all-reports-sss-loans", [ReportsSSSController::class, "getAllReportsSSSLoans"]);
    Route::get("/get-reports-sss-loan-types", [ReportsSSSController::class, "getReportsSSSLoanTypes"]);
    Route::get("/export-all-reports-sss-contributions", [ReportsSSSController::class, "exportAllReportsSSSContributions"]);
    Route::get("/export-all-reports-sss-loans", [ReportsSSSController::class, "exportAllReportsSSSLoans"]);

    //BDO cash card
    Route::get("/get-all-reports-bdo-cash-card", [ReportsBDOCashCardController::class, "getAllReportsBDOCashCard"]);
    Route::get("/get-all-reports-bdo-cash-card-history", [ReportsBDOCashCardController::class, "getAllReportsBDOCashCardHistory"]);
    Route::get("/export-all-reports-bdo-cash-card", [ReportsBDOCashCardController::class, "exportAllReportsBDOCashCard"]);
    Route::get("/export-all-reports-bdo-cash-card-history", [ReportsBDOCashCardController::class, "exportAllReportsBDOCashCardHistory"]);

    //pay slip
    Route::middleware(["permission:perform_reports_pay_slip"])->post("/submit-payslips", [PayslipController::class, "submitPayslip"]);
    Route::middleware(["permission:perform_reports_pay_slip"])->post("/export-payslips", [PayslipController::class, "exportPayslip"]);

    //13th Month Pay
    Route::get("/get-all-thirteenth-month-pay", [ReportsThirteenthMonthController::class, "getAllThirteenthMonth"]);
    Route::get("/get-thirteenth-month-pay/{id}", [ReportsThirteenthMonthController::class, "getThirteenthMonthPay"]);
    Route::get("/get-employees-monthly-allocation/{id}", [ReportsThirteenthMonthController::class, "getEmployeesMonthlyAllocation"]);
    Route::get("/get-branches-monthly-allocation/{id}", [ReportsThirteenthMonthController::class, "getBranchesMonthlyAllocation"]);
    Route::get("/get-thirteenth-month-monthly-allocation/{id}", [ReportsThirteenthMonthController::class, "getThirteenthMonthMonthlyAllocation"]);
    Route::post("/generate-thirteenth-month-pay", [ReportsThirteenthMonthController::class, "generateThirteenthMonthPay"]);
    Route::post("/generate-thirteenth-month-monthly-allocation", [ReportsThirteenthMonthController::class, "generateThirteenthMonthMonthlyAllocation"]);
    Route::middleware(["permission:perform_reports_13th_month_pay_entries"])->get("/generate-thirteenth-month-monthly-allocation-entries/{id}", [ReportsThirteenthMonthController::class, "generateThirteenthMonthMonthlyAllocationEntries"]);
    Route::middleware(["permission:generate_13th_month_pay"])->get('/download-excel-file/{filename}', [ReportsThirteenthMonthController::class, 'downloadExcelFile']);

    //deduction reports
    Route::middleware(["permission:perform_reports_deductions"])->get('/export-deduction-reports', [ReportsDeductionController::class, 'exportDeductionReport']);

    //payroll statutory reports
    Route::get('/export-payroll-statutory', [ReportsPayrollStatutoryController::class, 'exportPayrollStatutory']);

    //incentive attendance report
    Route::middleware(["permission:perform_reports_incentive_attendance"])->get('/export-incentive-attendance', [ReportsIncentiveAttendanceController::class, 'exportIncentiveAttendance']);
    Route::middleware(["permission:perform_reports_incentive_report"])->get('/export-incentive-report', [IncentiveReportController::class, 'exportIncentiveReport']);

    //timekeeping summary look up to payroll list
    Route::middleware(["permission:perform_reports_timekeeping_summary_report"])->get('/export-timekeeping-summary-report', [TimeKeepingSummaryReportController::class, 'exportTimeKeepingSummaryReport']);
    Route::middleware(["permission:perform_reports_timekeeping_summary_report"])->get('/export-timekeeping-prooflist-report',[TimekeepingProoflistReportController::class,'exportTimeKeepingProoflistReport']);

    //bank account history export
    Route::middleware(["permission:perform_reports_bank_account_history"])->get("/export-bank-account-history", [BankAccountController::class, "exportBankAccountHistory"]);

    //BIR 1601-C monthly remittance return
    Route::middleware(['permission:view_reports_bir_1601c'])->get('/bir-1601c', [Bir1601CController::class, 'index']);
    Route::middleware(['permission:view_reports_bir_1601c'])->get('/bir-1601c/{id}', [Bir1601CController::class, 'show']);
    Route::middleware(['permission:perform_reports_bir_1601c'])->post('/bir-1601c/generate', [Bir1601CController::class, 'generate']);
    Route::middleware(['permission:perform_reports_bir_1601c'])->post('/bir-1601c/{id}', [Bir1601CController::class, 'update']);
    Route::middleware(['permission:perform_reports_bir_1601c'])->post('/bir-1601c/{id}/finalize', [Bir1601CController::class, 'finalize']);
    Route::middleware(['permission:view_reports_bir_1601c'])->get('/export-bir-1601c/{id}', [Bir1601CController::class, 'export']);

    //BIR 2316 annual certificate of compensation payment/tax withheld
    Route::middleware(['permission:view_reports_bir_2316'])->get('/bir-2316', [Bir2316Controller::class, 'index']);
    Route::middleware(['permission:view_reports_bir_2316'])->get('/bir-2316/signatory', [Bir2316Controller::class, 'signatory']);
    Route::middleware(['permission:view_reports_bir_2316'])->get('/bir-2316/{id}', [Bir2316Controller::class, 'show']);
    Route::middleware(['permission:perform_reports_bir_2316'])->post('/bir-2316/import', [Bir2316Controller::class, 'import']);
    Route::middleware(['permission:view_reports_bir_2316'])->get('/export-bir-2316/{id}', [Bir2316Controller::class, 'export']);
});
