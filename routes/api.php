<?php

use App\Http\Controllers\Admin\ActivityLogsController;
use App\Http\Controllers\Admin\RolesPermissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employees\EmployeeBankAccountController;
use App\Http\Controllers\Employees\EmployeeBranchControlller;
use App\Http\Controllers\Employees\EmployeeContractControlller;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeHealthSafetyController;
use App\Http\Controllers\Employees\EmployeeOffensesControlller;
use App\Http\Controllers\Employees\EmployeePositionControlller;
use App\Http\Controllers\Employees\EmployeeRequirementsController;
use App\Http\Controllers\Employees\EmployeeTrainingController;
use App\Http\Controllers\Employees\EmployeeVaccinationController;
use App\Http\Controllers\Maintenance\AgencyController;
use App\Http\Controllers\Maintenance\BankAccountTypeController;
use App\Http\Controllers\Maintenance\BranchController;
use App\Http\Controllers\Maintenance\CoeReasonController;
use App\Http\Controllers\Maintenance\DiagnosisController;
use App\Http\Controllers\Maintenance\EmployeeStatusController;
use App\Http\Controllers\Maintenance\OffenseController;
use App\Http\Controllers\Maintenance\PositionController;
use App\Http\Controllers\Maintenance\RateController;
use App\Http\Controllers\Maintenance\SalaryPositionController;
use App\Http\Controllers\Maintenance\StationController;
use App\Http\Controllers\Maintenance\TrainingTitleController;
use App\Http\Controllers\Maintenance\VaccineController;
use App\Http\Controllers\Reports\ReportsEmploymentStatusController;
use App\Http\Controllers\Reports\ReportsHealthSafetyController;
use App\Http\Controllers\Reports\ReportsOffensesController;
use App\Http\Controllers\Reports\ReportsTardinessController;
use App\Http\Controllers\Reports\ReportsVaccinationController;
use App\Http\Controllers\Reports\ReportCoeRequestsController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, "login"]);
Route::middleware('auth:sanctum')->group( function () {

    Route::get('/logout', [AuthController::class,'logout']);
    Route::post('/change-password', [AuthController::class,'changePassword']);
    Route::get("/get-summary-details", [DashboardController::class, "getSummaryDetails"]);
    Route::get("/get-employees-birthmonth", [DashboardController::class, "getAllEmployeeBirthMonth"]);

    // ADMINISTRATOR MODULE
    //roles and permission
    Route::get("/get-all-activity-logs", [ActivityLogsController::class, "getAllActivityLogs"]);
    Route::get("/get-all-roles-and-permissions", [RolesPermissionController::class, "getAllRolesAndPermissions"]);
    Route::get("/get-all-permissions", [RolesPermissionController::class, "getAllPermissions"]);
    Route::get("/get-all-roles", [RolesPermissionController::class,"getAllRoles"]);
    Route::middleware(["permission:can_perform_roles_and_permissions"])->post("/update-or-create-roles-and-permissions", [RolesPermissionController::class, "updateOrCreateNewRole"]);
    Route::middleware(["permission:can_perform_roles_and_permissions"])->get("/delete-role/{id}", [RolesPermissionController::class,"deleteRolesAndPermissions"]);
    //users
    Route::get('/get-all-users', [UserController::class, "getAllUsers"]);
    Route::middleware(["permission:can_perform_users"])->post('/create-user', [AuthController::class, 'createUser']);
    Route::middleware(["permission:can_perform_users"])->post("/edit-user/{id}", [UserController::class,"editUser"]);

    // MAINTENANCE MODULE
    //stations
    Route::get("/get-all-stations", [StationController::class, "getAllStation"]);
    Route::get("/export-all-stations", [StationController::class, "exportAllStation"]);
    Route::get("/get-stations", [StationController::class, "getStations"]);
    Route::middleware(["permission:can_perform_stations"])->get("/delete-station/{id}", [StationController::class,"deleteStation"]);
    Route::middleware(["permission:can_perform_stations"])->post("/edit-station/{id}", [StationController::class,"editStation"]);
    Route::middleware(["permission:can_perform_stations"])->post("/create-station", [StationController::class,"createStation"]);
    //employee status
    Route::get("/get-all-status", [EmployeeStatusController::class, "getAllEmployeStatus"]);
    Route::get("/get-status", [EmployeeStatusController::class, "getEmployeeStatus"]);
    Route::get("/export-all-status", [EmployeeStatusController::class, "exportEmployeeStatus"]);
    Route::middleware(["permission:can_perform_employee_status"])->post("/create-employee-status", [EmployeeStatusController::class, "createEmployeeStatus"]);
    Route::middleware(["permission:can_perform_employee_status"])->post("/edit-employee-status/{id}", [EmployeeStatusController::class, "editEmployeeStatus"]);
    Route::middleware(["permission:can_perform_employee_status"])->get("/delete-employee-status/{id}", [EmployeeStatusController::class, "deleteEmployeeStatus"]);
    //positions
    Route::get("/get-all-positions", [PositionController::class, "getAllPositions"]);
    Route::get("/get-positions", [PositionController::class, "getPositions"]);
    Route::get("/export-all-positions", [PositionController::class, "exportAllPositions"]);
    Route::middleware(["permission:can_perform_stations"])->post("/create-position", [PositionController::class,"createPosition"]);
    Route::middleware(["permission:can_perform_stations"])->post("/edit-position/{id}", [PositionController::class,"editPosition"]);
    //agency
    Route::get("/get-all-agencies", [AgencyController::class, "getAllAgencies"]);
    Route::get("/get-agencies", [AgencyController::class, "getAgencies"]);
    Route::get("/export-all-agencies", [AgencyController::class, "exportAllAgencies"]);
    Route::middleware(["permission:can_perform_agencies"])->post("/create-agency", [AgencyController::class, "createAgency"]);
    Route::middleware(["permission:can_perform_agencies"])->post("/edit-agency/{id}", [AgencyController::class, "editAgency"]);
    //offenses
    Route::get("/get-all-offenses", [OffenseController::class, "getAllOffenses"]);
    Route::get("/get-all-categories", [OffenseController::class, "getAllCategories"]);
    Route::get("/get-all-subcategories", [OffenseController::class, "getAllSubCategories"]);
    Route::get("/get-subcategories", [OffenseController::class, "getSubCategories"]);
    Route::get("/get-categories", [OffenseController::class, "getCategories"]);
    Route::get("/get-offenses", [OffenseController::class, "getOffenses"]);
    Route::get("/get-sanctions", [OffenseController::class, "getSanctions"]);
    Route::get("/export-all-offenses", [OffenseController::class, "exportAllOffenses"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/create-offense", [OffenseController::class, "createOffense"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/edit-offense/{id}", [OffenseController::class, "editOffense"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/create-category", [OffenseController::class, "createCategory"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/edit-category/{id}", [OffenseController::class, "editCategory"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/create-subcategory", [OffenseController::class, "createSubCategory"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/edit-subcategory/{id}", [OffenseController::class, "editSubCategory"]);
    //branches
    Route::get("/get-all-branches", [BranchController::class, "getAllBranches"]);
    Route::get("/get-branches", [BranchController::class, "getBranches"]);
    Route::get("/export-all-branches", [BranchController::class, "exportBranches"]);
    Route::middleware(["permission:can_perform_branches"])->post("/create-branch", [BranchController::class, "createBranch"]);
    Route::middleware(["permission:can_perform_branches"])->post("/edit-branch/{id}", [BranchController::class, "editBranch"]);
    //training titles
    Route::get("/get-all-training-titles", [TrainingTitleController::class, "getAllTrainingTitles"]);
    Route::get("/get-training-titles", [TrainingTitleController::class, "getTrainingTitles"]);
    Route::get("/export-all-training-titles", [TrainingTitleController::class, "exportAllTrainingTitles"]);
    Route::middleware(["permission:can_perform_training_titles"])->post("/create-training-title", [TrainingTitleController::class, "createTrainingTitle"]);
    Route::middleware(["permission:can_perform_training_titles"])->post("/edit-training-title/{id}", [TrainingTitleController::class, "editTrainingTitle"]);
    //diagnosis
    Route::get("/get-all-diagnosis", [DiagnosisController::class, "getAllDiagnosis"]);
    Route::get("/get-diagnosis", [DiagnosisController::class, "getDiagnosis"]);
    Route::get("/export-all-diagnosis", [DiagnosisController::class, "exportAllDiagnosis"]);
    Route::middleware(["permission:can_perform_diagnosis"])->post("/create-diagnosis", [DiagnosisController::class, "createDiagnosis"]);
    Route::middleware(["permission:can_perform_diagnosis"])->post("/edit-diagnosis/{id}", [DiagnosisController::class, "editDiagnosis"]);
    //rate per group
    Route::get("/get-all-rates", [RateController::class, "getAllRates"]);
    Route::get("/export-all-rates", [RateController::class, "exportAllRates"]);
    Route::get("/get-rates", [RateController::class, "getRates"]);
    Route::middleware(["permission:can_perform_rate_per_group"])->post("/create-rate", [RateController::class, "createRate"]);
    Route::middleware(["permission:can_perform_rate_per_group"])->post("/edit-rate/{id}", [RateController::class, "editRate"]);
    //bank account types
    Route::get("/get-all-bank-account-types", [BankAccountTypeController::class, "getAllBankAccountTypes"]);
    Route::get("/export-all-bank-account-types", [BankAccountTypeController::class, "exportAllBankAccountTypes"]);
    Route::get("/get-bank-account-types", [BankAccountTypeController::class, "getBankAccountTypes"]);
    Route::middleware(["permission:can_perform_bank_account_types"])->post("/create-bank-account-type", [BankAccountTypeController::class, "createBankAccountType"]);
    Route::middleware(["permission:can_perform_bank_account_types"])->post("/edit-bank-account-type/{id}", [BankAccountTypeController::class, "editBankAccountType"]);
    //salary positions
    Route::get("/get-all-salary-positions", [SalaryPositionController::class, "getAllSalaryPositions"]);
    Route::get("/export-all-salary-positions", [SalaryPositionController::class, "exportAlLSalaryPositions"]);
    Route::get("/get-salary-positions", [SalaryPositionController::class, "getSalaryPositions"]);
    Route::middleware(["permission:can_perform_salary_positions"])->post("/create-salary-position", [SalaryPositionController::class, "createSalaryPosition"]);
    Route::middleware(["permission:can_perform_salary_positions"])->post("/edit-salary-position/{id}", [SalaryPositionController::class, "editSalaryPosition"]);
    //vaccines
    Route::get("/get-all-vaccines", [VaccineController::class, "getAllVaccines"]);
    Route::get("/export-all-vaccines", [VaccineController::class, "exportAllVaccines"]);
    Route::get("/get-vaccines", [VaccineController::class, "getVaccines"]);
    Route::middleware(["permission:can_perform_vaccine_brand"])->post("/create-vaccine", [VaccineController::class, "createVaccine"]);
    Route::middleware(["permission:can_perform_vaccine_brand"])->post("/edit-vaccine/{id}", [VaccineController::class, "editVaccine"]);
    //coe reasonse
    Route::get("/get-all-coe-reasons", [CoeReasonController::class, "getAllCoeReasons"]);
    Route::get("/get-coe-reasons", [CoeReasonController::class, "getCoeReasons"]);
    Route::get("/export-all-coe-reasons", [CoeReasonController::class, "exportAllCoeReasons"]);
    Route::middleware(["permission:can_perform_coe_reasons"])->post("/create-coe-reason", [CoeReasonController::class, "createCoeReason"]);
    Route::middleware(["permission:can_perform_coe_reasons"])->post("/edit-coe-reason/{id}", [CoeReasonController::class, "editCoeReason"]);

    // EMPLOYEES MODULE
    Route::get("/get-all-employees", [EmployeeController::class, "getAllEmployees"]);
    Route::get("/get-employees", [EmployeeController::class, "getEmployees"]);
    Route::get("/get-employees-list", [EmployeeController::class, "getEmployeeList"]);
    Route::get("/get-employee/{employee_number}", [EmployeeController::class, "getEmployee"]);
    Route::get("/export-employees-list", [EmployeeController::class, "exportEmployee"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/add-employee", [EmployeeController::class, "addEmployee"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/update-employee/{employee_number}", [EmployeeController::class, "updateEmployeeDetails"]);
    //audit logs
    Route::get("/get-employee-audit-logs/{employee_number}", [EmployeeController::class, "getEmployeeAuditLogs"]);
    //positions history
    Route::get("/get-employee-positions/{employee_number}", [EmployeePositionControlller::class, "getEmployeePositionHistory"]);
    Route::middleware(["permission:can_perform_employee_position"])->post("/add-employee-position/{employee_number}", [EmployeePositionControlller::class, "addPosition"]);
    Route::middleware(["permission:can_perform_employee_position"])->post("/edit-employee-position/{employee_number}", [EmployeePositionControlller::class, "editPosition"]);
    //branches history
    Route::get("/get-employee-branches/{employee_number}", [EmployeeBranchControlller::class, "getEmployeeBranchHistory"]);
    Route::middleware(["permission:can_perform_employee_branches"])->post("/add-employee-branch/{employee_number}", [EmployeeBranchControlller::class, "addBranch"]);
    Route::middleware(["permission:can_perform_employee_branches"])->post("/edit-employee-branch/{employee_number}", [EmployeeBranchControlller::class, "editBranch"]);
    //contracts history
    Route::get("/get-employee-contracts/{employee_number}", [EmployeeContractControlller::class, "getEmployeeContractHistory"]);
    Route::middleware(["permission:can_perform_employee_contracts"])->post("/add-employee-contract/{employee_number}", [EmployeeContractControlller::class, "addContract"]);
    Route::middleware(["permission:can_perform_employee_contracts"])->post("/edit-employee-contract/{employee_number}", [EmployeeContractControlller::class, "editContract"]);
    Route::get("/get-employee-contract-details/{id}", [EmployeeContractControlller::class, "getContractDetails"]);
    //bank details history
    Route::get("/get-employee-bank-details/{employee_number}", [EmployeeBankAccountController::class, "getEmployeeBankDetailsHistory"]);
    Route::middleware(["permission:can_perform_employee_bank_details"])->post("/add-employee-bank-details/{employee_number}", [EmployeeBankAccountController::class, "addBankDetails"]);
    Route::middleware(["permission:can_perform_employee_bank_details"])->post("/edit-employee-bank-details/{employee_number}", [EmployeeBankAccountController::class, "editBankDetails"]);
    //offenses history
    Route::get("/get-employee-offenses/{employee_number}", [EmployeeOffensesControlller::class, "getEmployeeOffenses"]);
    Route::middleware(["permission:can_perform_employee_offenses"])->post("/add-employee-offense/{employee_number}", [EmployeeOffensesControlller::class, "addOffense"]);
    Route::middleware(["permission:can_perform_employee_offenses"])->post("/edit-employee-offense/{employee_number}", [EmployeeOffensesControlller::class, "editOffense"]);
    //requirements
    Route::get("/get-employee-requirements/{employee_number}", [EmployeeRequirementsController::class, "getEmployeeRequirements"]);
    //trainings history
    Route::get("/get-employee-trainings/{employee_number}", [EmployeeTrainingController::class, "getEmployeeTrainings"]);
    Route::middleware(["permission:can_perform_employee_requirements"])->post("/create-or-edit-employee-requirements/{employee_number}", [EmployeeRequirementsController::class, "updateOrCreateRequirements"]);
    Route::middleware(["permission:can_perform_employee_training_titles"])->post("/add-employee-training-title/{employee_number}", [EmployeeTrainingController::class, "addTrainingTitle"]);
    //vaccination
    Route::get("/get-employee-vaccination/{employee_number}", [EmployeeVaccinationController::class, "getEmployeeVaccines"]);
    Route::middleware(["permission:can_perform_employee_vaccination"])->post("/add-employee-vaccination/{employee_number}", [EmployeeVaccinationController::class, "addVaccine"]);
    Route::middleware(["permission:can_perform_employee_vaccination"])->post("/edit-employee-vaccination/{employee_number}", [EmployeeVaccinationController::class, "editVaccine"]);
    //health and safety
    Route::get("/get-employee-health-safety/{employee_number}", [EmployeeHealthSafetyController::class, "getEmployeeHealthSafety"]);
    Route::middleware(["permission:can_perform_employee_health_and_safety"])->post("/add-employee-health-safety/{employee_number}", [EmployeeHealthSafetyController::class, "addEmployeeHealthSafety"]);
    Route::middleware(["permission:can_perform_employee_health_and_safety"])->post("/edit-employee-health-safety/{employee_number}", [EmployeeHealthSafetyController::class, "editEmployeeHealthSafety"]);


    //REPORTS MODULE
    //employee offenses
    Route::get("/get-all-reports-offenses", [ReportsOffensesController::class, "getAllEmployeeOffenses"]);
    //vaccination
    Route::get("/get-all-reports-vaccination", [ReportsVaccinationController::class, "getAllReportsVaccines"]);
    //health and safety
    Route::get("/get-all-reports-health-and-safety", [ReportsHealthSafetyController::class, "getAllReportsHealthAndSafety"]);
    //coe requests
    Route::get("/get-all-reports-coe-requests", [ReportCoeRequestsController::class, "getAllCoeRequests"]);
    Route::get("/get-reports-coe-request/{id}", [ReportCoeRequestsController::class, "getCoeRequest"]);
    Route::middleware(["permission:can_perform_all_records_coe_requests"])->post("/add-reports-coe-request", [ReportCoeRequestsController::class, "createCoeRequest"]);
    Route::middleware(["permission:can_perform_all_records_coe_requests"])->post("/edit-reports-coe-request/{id}", [ReportCoeRequestsController::class, "editCoeRequest"]);
    Route::middleware(["permission:can_perform_all_records_coe_requests"])->get("/cancel-reports-coe-request/{id}", [ReportCoeRequestsController::class, "cancelCoeRequest"]);
    Route::middleware(["permission:can_perform_all_records_coe_requests"])->get("/approve-reports-coe-request/{id}", [ReportCoeRequestsController::class, "approveCoeRequest"]);
    //tardiness
    Route::get("/get-all-reports-tardiness", [ReportsTardinessController::class, "getAllReportsVaccines"]);
    Route::middleware(["permission:can_perform_all_records_tardiness"])->post("/void-reports-tardiness/{id}", [ReportsTardinessController::class, "voidTardiness"]);
    Route::middleware(["permission:can_perform_all_records_tardiness"])->get("/approve-reports-tardiness/{id}", [ReportsTardinessController::class, "approveTardiness"]);
    Route::middleware(["permission:can_import_all_records_tardiness"])->post("/import-reports-tardiness", [ReportsTardinessController::class, "importTardinessExcel"]);
    Route::get("/get-tardiness-details/{id}", [ReportsTardinessController::class, "getTardinessDetails"]);
    Route::middleware(["permission:can_perform_all_records_tardiness"])->post("/approve-reports-all-tardiness", [ReportsTardinessController::class, "approveBulkTardiness"]);
    Route::middleware(["permission:can_perform_all_records_tardiness"])->post("/edit-reports-minutes-tardiness", [ReportsTardinessController::class, "editMinutesOfTardiness"]);
    Route::middleware(["permission:can_perform_all_records_tardiness"])->post("/send-report-tardiness-nte/{id}", [ReportsTardinessController::class, "sendNTE"]);
    Route::middleware(["permission:can_perform_all_records_tardiness"])->post("/send-report-tardiness-nod/{id}", [ReportsTardinessController::class, "sendNOD"]);
    Route::get("/get-reports-nte-details/{id}", [ReportsTardinessController::class, "getNTEDetails"]);
    //employment status
    Route::middleware(["permission:can_perform_all_records_employment_status"])->get("/get-employment-status-report", [ReportsEmploymentStatusController::class, "getEmploymentStatusReports"]);

});


