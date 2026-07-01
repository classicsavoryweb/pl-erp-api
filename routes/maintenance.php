<?php

use App\Http\Controllers\Maintenance\ActingCapacityMaintenanceController;
use App\Http\Controllers\Maintenance\AdditionalIncomeController;
use App\Http\Controllers\Maintenance\AdjustmentTypeController;
use App\Http\Controllers\Maintenance\ATDAccessTypeController;
use App\Http\Controllers\Maintenance\AgencyController;
use App\Http\Controllers\Maintenance\BankAccountTypeController;
use App\Http\Controllers\Maintenance\BranchController;
use App\Http\Controllers\Maintenance\CoeReasonController;
use App\Http\Controllers\Maintenance\CSHubTransactionTypeController;
use App\Http\Controllers\Maintenance\CSHubModulesController;
use App\Http\Controllers\Maintenance\CSHubSourceTypeController;
use App\Http\Controllers\Maintenance\DeductionsController;
use App\Http\Controllers\Maintenance\IncentivesManagementController;
use App\Http\Controllers\Maintenance\IncentivesBracketController;
use App\Http\Controllers\Maintenance\IncentivesPerEmployeeController;
use App\Http\Controllers\Maintenance\ContributionsAndTaxController;
use App\Http\Controllers\Maintenance\DiagnosisController;
use App\Http\Controllers\Maintenance\EmployeeStatusController;
use App\Http\Controllers\Maintenance\HolidayController;
use App\Http\Controllers\Maintenance\IncentivesPositionController;
use App\Http\Controllers\Maintenance\OffenseController;
use App\Http\Controllers\Maintenance\PagibigController;
use App\Http\Controllers\Maintenance\PeriodDefinitionController;
use App\Http\Controllers\Maintenance\PhilhealthController;
use App\Http\Controllers\Maintenance\PhilhealthPercentageBaseController;
use App\Http\Controllers\Maintenance\PositionController;
use App\Http\Controllers\Maintenance\RateController;
use App\Http\Controllers\Maintenance\RegionController;
use App\Http\Controllers\Maintenance\SalaryPositionController;
use App\Http\Controllers\Maintenance\SanctionsController;
use App\Http\Controllers\Maintenance\ScheduleMasterlistController;
use App\Http\Controllers\Maintenance\SSSController;
use App\Http\Controllers\Maintenance\StationController;
use App\Http\Controllers\Maintenance\TardinessSanctionController;
use App\Http\Controllers\Maintenance\TaxController;
use App\Http\Controllers\Maintenance\TermsController;
use App\Http\Controllers\Maintenance\LastPaySetupController;
use App\Http\Controllers\Maintenance\TrainingTitleController;
use App\Http\Controllers\Maintenance\VaccineController;
use App\Http\Controllers\Maintenance\SizesController;
use App\Http\Controllers\Maintenance\SupplierController;
use App\Http\Controllers\Maintenance\UniformFreeUniformLimitController;
use App\Http\Controllers\Maintenance\UniformPeriodLockController;
use Illuminate\Support\Facades\Route;

// MAINTENANCE MODULE
Route::middleware('auth:sanctum')->group(function() {
    //stations
    Route::get("/get-all-stations", [StationController::class, "getAllStation"]);
    Route::get("/export-all-stations", [StationController::class, "exportAllStation"]);
    Route::get("/get-stations", [StationController::class, "getStations"]);
    Route::middleware(["permission:perform_stations"])->get("/delete-station/{id}", [StationController::class,"deleteStation"]);
    Route::middleware(["permission:perform_stations"])->post("/edit-station/{id}", [StationController::class,"editStation"]);
    Route::middleware(["permission:perform_stations"])->post("/create-station", [StationController::class,"createStation"]);
    //employee status
    Route::get("/get-all-status", [EmployeeStatusController::class, "getAllEmployeStatus"]);
    Route::get("/get-status", [EmployeeStatusController::class, "getEmployeeStatus"]);
    Route::get("/export-all-status", [EmployeeStatusController::class, "exportEmployeeStatus"]);
    Route::middleware(["permission:perform_employee_status"])->post("/create-employee-status", [EmployeeStatusController::class, "createEmployeeStatus"]);
    Route::middleware(["permission:perform_employee_status"])->post("/edit-employee-status/{id}", [EmployeeStatusController::class, "editEmployeeStatus"]);
    Route::middleware(["permission:perform_employee_status"])->get("/delete-employee-status/{id}", [EmployeeStatusController::class, "deleteEmployeeStatus"]);
    //positions
    Route::get("/get-all-positions", [PositionController::class, "getAllPositions"]);
    Route::get("/get-positions", [PositionController::class, "getPositions"]);
    Route::get("/export-all-positions", [PositionController::class, "exportAllPositions"]);
    Route::middleware(["permission:perform_positions"])->post("/create-position", [PositionController::class,"createPosition"]);
    Route::middleware(["permission:perform_positions"])->post("/edit-position/{id}", [PositionController::class,"editPosition"]);
    //agency
    Route::get("/get-all-agencies", [AgencyController::class, "getAllAgencies"]);
    Route::get("/get-agencies", [AgencyController::class, "getAgencies"]);
    Route::get("/export-all-agencies", [AgencyController::class, "exportAllAgencies"]);
    Route::middleware(["permission:perform_agencies"])->post("/create-agency", [AgencyController::class, "createAgency"]);
    Route::middleware(["permission:perform_agencies"])->post("/edit-agency/{id}", [AgencyController::class, "editAgency"]);
    //regions
    Route::middleware(["permission:view_region"])->get("/get-all-regions", [RegionController::class, "getAllRegions"]);
    Route::middleware(["permission:view_region"])->get("/get-regions", [RegionController::class, "getRegions"]);
    Route::middleware(["permission:view_region"])->get("/export-all-regions", [RegionController::class, "exportAllRegions"]);
    Route::middleware(["permission:perform_region"])->post("/create-region", [RegionController::class, "createRegion"]);
    Route::middleware(["permission:perform_region"])->post("/edit-region/{id}", [RegionController::class, "editRegion"]);
    //offenses
    Route::get("/get-all-offenses", [OffenseController::class, "getAllOffenses"]);
    Route::get("/get-all-categories", [OffenseController::class, "getAllCategories"]);
    Route::get("/get-all-subcategories", [OffenseController::class, "getAllSubCategories"]);
    Route::get("/get-subcategories", [OffenseController::class, "getSubCategories"]);
    Route::get("/get-categories", [OffenseController::class, "getCategories"]);
    Route::get("/get-offenses", [OffenseController::class, "getOffenses"]);
    Route::get("/export-all-offenses", [OffenseController::class, "exportAllOffenses"]);
    Route::middleware(["permission:perform_offenses"])->post("/create-offense", [OffenseController::class, "createOffense"]);
    Route::middleware(["permission:perform_offenses"])->post("/edit-offense/{id}", [OffenseController::class, "editOffense"]);
    Route::middleware(["permission:perform_offenses"])->post("/create-category", [OffenseController::class, "createCategory"]);
    Route::middleware(["permission:perform_offenses"])->post("/edit-category/{id}", [OffenseController::class, "editCategory"]);
    Route::middleware(["permission:perform_offenses"])->post("/create-subcategory", [OffenseController::class, "createSubCategory"]);
    Route::middleware(["permission:perform_offenses"])->post("/edit-subcategory/{id}", [OffenseController::class, "editSubCategory"]);
    //branches
    Route::get("/get-all-branches", [BranchController::class, "getAllBranches"]);
    Route::get("/get-branches", [BranchController::class, "getBranches"]);
    Route::get("/get-filter-branches", [BranchController::class, "getFilterBranches"]);
    Route::get("/get-branch-activities/{id}", [BranchController::class, "getBranchActivities"]);
    Route::get("/export-all-branches", [BranchController::class, "exportBranches"]);
    Route::middleware(["permission:create_branches"])->post("/create-branch", [BranchController::class, "createBranch"]);
    Route::middleware(["permission:edit_branches_all|edit_branches_oic"])->post("/edit-branch/{id}", [BranchController::class, "editBranch"]);
    //training titles
    Route::get("/get-all-training-titles", [TrainingTitleController::class, "getAllTrainingTitles"]);
    Route::get("/get-training-titles", [TrainingTitleController::class, "getTrainingTitles"]);
    Route::get("/export-all-training-titles", [TrainingTitleController::class, "exportAllTrainingTitles"]);
    Route::middleware(["permission:perform_training_titles"])->post("/create-training-title", [TrainingTitleController::class, "createTrainingTitle"]);
    Route::middleware(["permission:perform_training_titles"])->post("/edit-training-title/{id}", [TrainingTitleController::class, "editTrainingTitle"]);
    //diagnosis
    Route::get("/get-all-diagnosis", [DiagnosisController::class, "getAllDiagnosis"]);
    Route::get("/get-diagnosis", [DiagnosisController::class, "getDiagnosis"]);
    Route::get("/export-all-diagnosis", [DiagnosisController::class, "exportAllDiagnosis"]);
    Route::middleware(["permission:perform_diagnosis"])->post("/create-diagnosis", [DiagnosisController::class, "createDiagnosis"]);
    Route::middleware(["permission:perform_diagnosis"])->post("/edit-diagnosis/{id}", [DiagnosisController::class, "editDiagnosis"]);
    //rate per group
    Route::get("/get-all-rates", [RateController::class, "getAllRates"]);
    Route::get("/export-all-rates", [RateController::class, "exportAllRates"]);
    Route::get("/get-rates", [RateController::class, "getRates"]);
    Route::middleware(["permission:perform_rate_per_group"])->post("/create-rate", [RateController::class, "createRate"]);
    Route::middleware(["permission:perform_rate_per_group"])->post("/edit-rate/{id}", [RateController::class, "editRate"]);
    //bank account types
    Route::get("/get-all-bank-account-types", [BankAccountTypeController::class, "getAllBankAccountTypes"]);
    Route::get("/export-all-bank-account-types", [BankAccountTypeController::class, "exportAllBankAccountTypes"]);
    Route::get("/get-bank-account-types", [BankAccountTypeController::class, "getBankAccountTypes"]);
    Route::middleware(["permission:perform_bank_account_types"])->post("/create-bank-account-type", [BankAccountTypeController::class, "createBankAccountType"]);
    Route::middleware(["permission:perform_bank_account_types"])->post("/edit-bank-account-type/{id}", [BankAccountTypeController::class, "editBankAccountType"]);
    //salary positions
    Route::get("/get-all-salary-positions", [SalaryPositionController::class, "getAllSalaryPositions"]);
    Route::get("/export-all-salary-positions", [SalaryPositionController::class, "exportAlLSalaryPositions"]);
    Route::get("/get-salary-positions", [SalaryPositionController::class, "getSalaryPositions"]);
    Route::middleware(["permission:perform_salary_positions"])->post("/create-salary-position", [SalaryPositionController::class, "createSalaryPosition"]);
    Route::middleware(["permission:perform_salary_positions"])->post("/edit-salary-position/{id}", [SalaryPositionController::class, "editSalaryPosition"]);
    //vaccines
    Route::get("/get-all-vaccines", [VaccineController::class, "getAllVaccines"]);
    Route::get("/export-all-vaccines", [VaccineController::class, "exportAllVaccines"]);
    Route::get("/get-vaccines", [VaccineController::class, "getVaccines"]);
    Route::middleware(["permission:perform_vaccine_brand"])->post("/create-vaccine", [VaccineController::class, "createVaccine"]);
    Route::middleware(["permission:perform_vaccine_brand"])->post("/edit-vaccine/{id}", [VaccineController::class, "editVaccine"]);
    //coe reasons
    Route::get("/get-all-coe-reasons", [CoeReasonController::class, "getAllCoeReasons"]);
    Route::get("/get-coe-reasons", [CoeReasonController::class, "getCoeReasons"]);
    Route::get("/export-all-coe-reasons", [CoeReasonController::class, "exportAllCoeReasons"]);
    Route::middleware(["permission:perform_coe_reasons"])->post("/create-coe-reason", [CoeReasonController::class, "createCoeReason"]);
    Route::middleware(["permission:perform_coe_reasons"])->post("/edit-coe-reason/{id}", [CoeReasonController::class, "editCoeReason"]);
    //acting capacity reasons
    Route::get("/get-all-acting-capacity-reasons", [ActingCapacityMaintenanceController::class, "getAllActingCapacityReasons"]);
    Route::get("/get-acting-capacity-reasons", [ActingCapacityMaintenanceController::class, "getActingCapacityReasons"]);
    Route::get("/export-all-acting-capacity-reasons", [ActingCapacityMaintenanceController::class, "exportActingCapacityReasons"]);
    Route::middleware(["permission:perform_acting_capacity_reasons"])->post("/create-acting-capacity-reason", [ActingCapacityMaintenanceController::class, "createActingCapacityReason"]);
    Route::middleware(["permission:perform_acting_capacity_reasons"])->post("/edit-acting-capacity-reason/{id}", [ActingCapacityMaintenanceController::class, "editActingCapacityReason"]);
    //sanctions
    Route::get("/get-all-sanctions", [SanctionsController::class, "getAllSanctions"]);
    Route::get("/get-sanctions", [SanctionsController::class, "getSanctions"]);
    Route::get("/export-all-sanctions", [SanctionsController::class, "exportSanctions"]);
    Route::middleware(["permission:perform_sanctions"])->post("/create-sanction", [SanctionsController::class, "createSanction"]);
    Route::middleware(["permission:perform_sanctions"])->post("/edit-sanction/{id}", [SanctionsController::class, "editSanction"]);
    //schedule masterlist
    Route::get("/get-all-schedule-masterlist", [ScheduleMasterlistController::class, "getAllScheduleMasterlist"]);
    Route::get("/get-schedule-masterlist", [ScheduleMasterlistController::class, "getScheduleMasterlist"]);
    Route::get("/export-all-schedule-masterlist", [ScheduleMasterlistController::class, "exportScheduleMasterlist"]);
    Route::middleware(["permission:perform_schedule_masterlist"])->post("/create-schedule-masterlist", [ScheduleMasterlistController::class, "createScheduleMasterlist"]);
    Route::middleware(["permission:perform_schedule_masterlist"])->post("/edit-schedule-masterlist/{id}", [ScheduleMasterlistController::class, "editScheduleMasterlist"]);
    //holidays
    Route::get("/get-all-holidays", [HolidayController::class, "getAllHolidays"]);
    Route::get("/get-holiday", [HolidayController::class, "getHoliday"]);
    Route::get("/export-all-holidays", [HolidayController::class, "exportHolidays"]);
    Route::middleware(["permission:perform_holidays"])->post("/create-holiday", [HolidayController::class, "createHoliday"]);
    Route::middleware(["permission:perform_holidays"])->post("/edit-holiday/{id}", [HolidayController::class, "editHoliday"]);
    //sss
    Route::get("/get-all-sss", [SSSController::class, "getAllSSS"]);
    Route::get("/get-sss", [SSSController::class, "getSSS"]);
    Route::get("/export-all-sss", [SSSController::class, "exportAllSSS"]);
    Route::middleware(["permission:perform_sss"])->post("/create-sss", [SSSController::class, "createSSS"]);
    Route::middleware(["permission:perform_sss"])->post("/edit-sss/{id}", [SSSController::class, "editSSS"]);
    //tax
    Route::get("/get-all-tax", [TaxController::class, "getAllTax"]);
    Route::get("/get-tax", [TaxController::class, "getTax"]);
    Route::get("/export-all-tax", [TaxController::class, "exportAllTax"]);
    Route::middleware(["permission:perform_tax"])->post("/create-tax", [TaxController::class, "createTax"]);
    Route::middleware(["permission:perform_tax"])->post("/edit-tax/{id}", [TaxController::class, "editTax"]);
    Route::get("/get-annual-tax-table", [TaxController::class, "getAllAnnualTax"]);
    Route::get("/export-annual-tax-table", [TaxController::class, "exportAllAnnualTax"]);

    //philhealth
    Route::get("/get-all-philhealth", [PhilhealthController::class, "getAllPhilhealth"]);
    Route::get("/get-philhealth", [PhilhealthController::class, "getPhilhealth"]);
    Route::get("/export-all-philhealth", [PhilhealthController::class, "exportAllPhilhealth"]);
    Route::middleware(["permission:perform_philhealth"])->post("/create-philhealth", [PhilhealthController::class, "createPhilhealth"]);
    Route::middleware(["permission:perform_philhealth"])->post("/edit-philhealth/{id}", [PhilhealthController::class, "editPhilhealth"]);
    //pagibig
    Route::get("/get-all-pagibig", [PagibigController::class, "getAllPagibig"]);
    Route::get("/get-pagibig", [PagibigController::class, "getPagibig"]);
    Route::get("/export-all-pagibig", [PagibigController::class, "exportAllPagibig"]);
    Route::middleware(["permission:perform_pagibig"])->post("/edit-pagibig/{id}", [PagibigController::class, "editPagibig"]);
    //deductions
    Route::get("/get-all-deductions", [DeductionsController::class, "getAllDeductions"]);
    Route::get("/get-deduction", [DeductionsController::class, "getDeduction"]);
    Route::get("/export-all-deductions", [DeductionsController::class, "exportAllDeductions"]);
    Route::middleware(["permission:perform_deductions"])->post("/create-deduction", [DeductionsController::class, "createDeduction"]);
    Route::middleware(["permission:perform_deductions"])->post("/edit-deduction/{id}", [DeductionsController::class, "editDeduction"]);
    //additional income
    Route::get("/get-all-additional-income", [AdditionalIncomeController::class, "getAllAdditionalIncome"]);
    Route::get("/get-additional-income", [AdditionalIncomeController::class, "getAdditionalIncome"]);
    Route::get("/export-all-additional-income", [AdditionalIncomeController::class, "exportAllAdditionalIncome"]);
    Route::middleware(["permission:perform_additional_income"])->post("/create-additional-income", [AdditionalIncomeController::class, "createAdditionalIncome"]);
    Route::middleware(["permission:perform_additional_income"])->post("/edit-additional-income/{id}", [AdditionalIncomeController::class, "editAdditionalIncome"]);
    //period definition
    Route::get("/get-all-period-definition", [PeriodDefinitionController::class, "getAllPeriodDefinition"]);
    Route::get("/get-period-definition", [PeriodDefinitionController::class, "getPeriodDefinition"]);
    Route::get("/export-all-period-definition", [PeriodDefinitionController::class, "exportAllPeriodDefinition"]);
    Route::middleware(["permission:perform_period_definition"])->post("/create-period-definition", [PeriodDefinitionController::class, "createPeriodDefinition"]);
    Route::middleware(["permission:perform_period_definition"])->post("/edit-period-definition/{id}", [PeriodDefinitionController::class, "editPeriodDefinition"]);
    //cs hub transaction types
    Route::get("/get-all-cshub-transaction-types", [CSHubTransactionTypeController::class, "getAllCsHubTransactionTypes"]);
    Route::get("/get-cshub-transaction-types", [CSHubTransactionTypeController::class, "getCsHubTransactionTypes"]);
    Route::get("/export-all-cshub-transaction-types", [CSHubTransactionTypeController::class, "exportCsHubTransactionTypes"]);
    Route::middleware(["permission:perform_cshub_transaction_type"])->post("/create-cshub-transaction-type", [CSHubTransactionTypeController::class, "createCsHubTransactionType"]);
    Route::middleware(["permission:perform_cshub_transaction_type"])->post("/edit-cshub-transaction-type/{id}", [CSHubTransactionTypeController::class, "editCsHubTransactionType"]);
    // atd access type
    Route::get("/get-all-atd-access-types", [ATDAccessTypeController::class, "getAllATDAccessTypes"]);
    Route::get("/export-all-atd-access-types", [ATDAccessTypeController::class, "exportAllATDAccessTypes"]);
    Route::post("/create-atd-access-type", [ATDAccessTypeController::class, "createATDAccessType"]);
    Route::post("/edit-atd-access-type/{id}", [ATDAccessTypeController::class, "editATDAccessType"]);
    //cs hub modules
    Route::get("/get-cshub-modules", [CSHubModulesController::class, "getCsHubModules"]);
    //cs hub source type
    Route::get("/get-trans-type", [CSHubSourceTypeController::class, "getTransType"]);
    Route::get("/get-exam-type", [CSHubSourceTypeController::class, "getExamType"]);
    //adjustment types
    Route::get("/get-all-adjustment-types", [AdjustmentTypeController::class, "getAllAdjustmentTypes"]);
    Route::get("/get-adjustment-types", [AdjustmentTypeController::class, "getAdjustmentTypes"]);
    Route::get("/export-all-adjustment-types", [AdjustmentTypeController::class, "exportAdjustmentTypes"]);
    Route::middleware(["permission:perform_adjustment_types"])->post("/create-adjustment-type", [AdjustmentTypeController::class, "createAdjustmentType"]);
    Route::middleware(["permission:perform_adjustment_types"])->post("/edit-adjustment-type/{id}", [AdjustmentTypeController::class, "editAdjustmentType"]);
    //incentives management
    Route::get("/get-all-incentives-management", [IncentivesManagementController::class, "getAllIncentivesManagement"]);
    Route::get("/get-incentives-management", [IncentivesManagementController::class, "getIncentivesManagement"]);
    Route::get("/export-all-incentives-management", [IncentivesManagementController::class, "exportAllIncentivesManagement"]);
    Route::middleware(["permission:perform_incentives_management"])->post("/create-incentives-management", [IncentivesManagementController::class, "createIncentivesManagement"]);
    Route::middleware(["permission:perform_incentives_management"])->post("/edit-incentives-management/{id}", [IncentivesManagementController::class, "editIncentivesManagement"]);
    //contributions and tax set-up
    Route::get("/get-all-contributions-and-tax", [ContributionsAndTaxController::class, "getAllContributionsAndTax"]);
    Route::get("/get-contribution-and-tax", [ContributionsAndTaxController::class, "getContributionAndTax"]);
    Route::get("/export-all-contributions-and-tax", [ContributionsAndTaxController::class, "exportAllContributionsAndTax"]);
    Route::middleware(["permission:perform_contributions_and_tax"])->post("/edit-contribution-and-tax/{id}", [ContributionsAndTaxController::class, "editContributionAndTax"]);
    //incentives bracket
    Route::get("/get-all-incentives-bracket", [IncentivesBracketController::class, "getAllIncentivesBracket"]);
    Route::get("/get-incentives-bracket", [IncentivesBracketController::class, "getIncentivesBracket"]);
    Route::get("/export-all-incentives-bracket", [IncentivesBracketController::class, "exportAllIncentivesBracket"]);
    Route::middleware(["permission:perform_incentives_bracket"])->post("/create-incentives-bracket", [IncentivesBracketController::class, "createIncentivesBracket"]);
    Route::middleware(["permission:perform_incentives_bracket"])->post("/edit-incentives-bracket/{id}", [IncentivesBracketController::class, "editIncentivesBracket"]);
    //incentives per employee
    Route::get("/get-all-incentives-per-employee", [IncentivesPerEmployeeController::class, "getAllIncentivesPerEmployee"]);
    Route::get("/get-incentives-per-employee", [IncentivesPerEmployeeController::class, "getIncentivesPerEmployee"]);
    Route::get("/export-all-incentives-per-employee", [IncentivesPerEmployeeController::class, "exportAllIncentivesPerEmployee"]);
    Route::middleware(["permission:perform_incentives_per_employee"])->post("/create-incentives-per-employee", [IncentivesPerEmployeeController::class, "createIncentivesPerEmployee"]);
    Route::middleware(["permission:perform_incentives_per_employee"])->post("/edit-incentives-per-employee/{id}", [IncentivesPerEmployeeController::class, "editIncentivesPerEmployee"]);
    //incentives position
    Route::get("/get-all-incentives-position", [IncentivesPositionController::class, "getAllIncentivesPosition"]);
    Route::get("/export-all-incentives-position", [IncentivesPositionController::class, "exportAllIncentivesPosition"]);
    Route::middleware(["permission:perform_incentives_position"])->post("/create-incentives-position", [IncentivesPositionController::class, "createIncentivesPosition"]);
    Route::middleware(["permission:perform_incentives_position"])->post("/edit-incentives-position/{id}", [IncentivesPositionController::class, "editIncentivesPosition"]);
    
    //terms
    Route::get("/get-all-terms", [TermsController::class, "getAllTerms"]);
    Route::get("/get-term", [TermsController::class, "getTerm"]);
    Route::get("/export-all-terms", [TermsController::class, "exportAllTerms"]);
    Route::middleware(["permission:perform_terms"])->post("/create-term", [TermsController::class, "createTerm"]);
    Route::middleware(["permission:perform_terms"])->post("/edit-term/{id}", [TermsController::class, "editTerm"]);
    //last pay setup
    Route::get("/get-all-last-pay-setup", [LastPaySetupController::class, "getAllLastPaySetup"]);
    Route::get("/get-last-pay-setup", [LastPaySetupController::class, "getLastPaySetup"]);
    Route::get("/export-all-last-pay-setup", [LastPaySetupController::class, "exportAllLastPaySetup"]);
    Route::middleware(["permission:perform_last_pay_setup"])->post("/create-last-pay-setup", [LastPaySetupController::class, "createLastPaySetup"]);
    Route::middleware(["permission:perform_last_pay_setup"])->post("/edit-last-pay-setup/{id}", [LastPaySetupController::class, "editLastPaySetup"]);
    //philhealth
    Route::get("/get-all-philhealth-percentage-base", [PhilhealthPercentageBaseController::class, "getAllPhilhealthPercentageBase"]);
    Route::get("/get-philhealth-percentage-base", [PhilhealthPercentageBaseController::class, "getPhilhealthPercentageBase"]);
    Route::middleware(["permission:perform_philhealth"])->post("/edit-philhealth-percentage-base/{id}", [PhilhealthPercentageBaseController::class, "editPhilhealthPercentageBase"]);
    //tardiness sanctions
    Route::get("/get-all-tardiness-sanctions", [TardinessSanctionController::class, "getAllTardinessSanctions"]);
    Route::get("/export-all-tardiness-sanctions", [TardinessSanctionController::class, "exportAllTardinessSanctions"]);
    Route::middleware(["permission:perform_tardiness_sanctions"])->post("/create-tardiness-sanction", [TardinessSanctionController::class, "createTardinessSanctions"]);
    Route::middleware(["permission:perform_tardiness_sanctions"])->post("/edit-tardiness-sanction/{id}", [TardinessSanctionController::class, "editTardinessSanctions"]);
    Route::get("/get-uniform-sizes", [SizesController::class, "getUniformSizes"]);
    Route::get("/export-uniform-sizes", [SizesController::class, "exportSizes"]);
    Route::middleware(["permission:perform_maintenance_sizes"])->post("/create-uniform-sizes", [SizesController::class, "createUniformSizes"]);
    Route::middleware(["permission:perform_maintenance_sizes"])->post("/edit-uniform-sizes/{id}", [SizesController::class, "editUniformSizes"]);
    Route::get("/get-uniform-supplier", [SupplierController::class, "getUniformSupplier"]);
    Route::get("/export-uniform-supplier", [SupplierController::class, "exportSupplier"]);
    Route::middleware(["permission:perform_maintenance_suppliers"])->post("/create-uniform-supplier", [SupplierController::class, "createUniformSupplier"]);
    Route::middleware(["permission:view_uniform_free_uniform_limit"])->get("/get-uniform-free-uniform-limits", [UniformFreeUniformLimitController::class, "getUniformFreeUniformLimits"]);
    Route::middleware(["permission:perform_uniform_free_uniform_limit"])->post("/create-uniform-free-uniform-limit", [UniformFreeUniformLimitController::class, "createUniformFreeUniformLimit"]);
    Route::middleware(["permission:perform_uniform_free_uniform_limit"])->post("/edit-uniform-free-uniform-limit/{id}", [UniformFreeUniformLimitController::class, "editUniformFreeUniformLimit"]);
    Route::middleware(["permission:view_uniform_period_lock"])->get("/get-uniform-period-locks", [UniformPeriodLockController::class, "getUniformPeriodLocks"]);
    Route::middleware(["permission:perform_uniform_period_lock"])->post("/save-uniform-period-lock-period", [UniformPeriodLockController::class, "saveUniformPeriodLockPeriod"]);

});
