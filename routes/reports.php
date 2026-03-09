<?php

use App\Http\Controllers\Compensation\PayslipController;
use App\Http\Controllers\Reports\ReportsThirteenthMonthController;
use App\Http\Controllers\Reports\ReportsPagibigController;
use App\Http\Controllers\Reports\ReportsPhilhealthController;
use App\Http\Controllers\Reports\ReportsSSSController;
use App\Http\Controllers\Reports\ReportsBDOCashCardController;
use App\Http\Controllers\Reports\ReportsDeductionController;
use App\Http\Controllers\Reports\ReportsIncentiveAttendanceController;
use App\Http\Controllers\Reports\ReportsPayrollStatutoryController;
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
});
