<?php

use App\Http\Controllers\Admin\ActivityLogsController;
use App\Http\Controllers\Admin\RolesPermissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Maintenance\AgencyController;
use App\Http\Controllers\Maintenance\BankAccountTypeController;
use App\Http\Controllers\Maintenance\BranchController;
use App\Http\Controllers\Maintenance\DiagnosisController;
use App\Http\Controllers\Maintenance\EmployeeStatusController;
use App\Http\Controllers\Maintenance\OffenseController;
use App\Http\Controllers\Maintenance\PositionController;
use App\Http\Controllers\Maintenance\RateController;
use App\Http\Controllers\Maintenance\SalaryPositionController;
use App\Http\Controllers\Maintenance\StationController;
use App\Http\Controllers\Maintenance\TrainingTitleController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, "login"]);
Route::middleware('auth:sanctum')->group( function () {

    Route::get('/logout', [AuthController::class,'logout']);
    Route::post('/change-password', [AuthController::class,'changePassword']);
    Route::get("/get-summary-details", [DashboardController::class, "getSummaryDetails"]);
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
    Route::get("/get-stations", [StationController::class, "getStations"]);
    Route::middleware(["permission:can_perform_stations"])->get("/delete-station/{id}", [StationController::class,"deleteStation"]);
    Route::middleware(["permission:can_perform_stations"])->post("/edit-station/{id}", [StationController::class,"editStation"]);
    Route::middleware(["permission:can_perform_stations"])->post("/create-station", [StationController::class,"createStation"]);
    //employee status
    Route::get("/get-all-status", [EmployeeStatusController::class, "getAllEmployeStatus"]);
    Route::get("/get-status", [EmployeeStatusController::class, "getEmployeeStatus"]);
    Route::middleware(["permission:can_perform_employee_status"])->post("/create-employee-status", [EmployeeStatusController::class, "createEmployeeStatus"]);
    Route::middleware(["permission:can_perform_employee_status"])->post("/edit-employee-status/{id}", [EmployeeStatusController::class, "editEmployeeStatus"]);
    Route::middleware(["permission:can_perform_employee_status"])->get("/delete-employee-status/{id}", [EmployeeStatusController::class, "deleteEmployeeStatus"]);
    //positions
    Route::get("/get-all-positions", [PositionController::class, "getAllPositions"]);
    Route::get("/get-positions", [PositionController::class, "getPositions"]);
    Route::middleware(["permission:can_perform_stations"])->post("/create-position", [PositionController::class,"createPosition"]);
    Route::middleware(["permission:can_perform_stations"])->post("/edit-position/{id}", [PositionController::class,"editPosition"]);
    //agency
    Route::get("/get-all-agencies", [AgencyController::class, "getAllAgencies"]);
    Route::get("/get-agencies", [AgencyController::class, "getAgencies"]);
    Route::middleware(["permission:can_perform_agencies"])->post("/create-agency", [AgencyController::class, "createAgency"]);
    Route::middleware(["permission:can_perform_agencies"])->post("/edit-agency/{id}", [AgencyController::class, "editAgency"]);
    //offenses
    Route::get("/get-all-offenses", [OffenseController::class, "getAllOffenses"]);
    Route::get("/get-all-categories", [OffenseController::class, "getAllCategories"]);
    Route::get("/get-all-subcategories", [OffenseController::class, "getAllSubCategories"]);
    Route::get("/get-subcategories", [OffenseController::class, "getSubCategories"]);
    Route::get("/get-categories", [OffenseController::class, "getCategories"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/create-offense", [OffenseController::class, "createOffense"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/edit-offense/{id}", [OffenseController::class, "editOffense"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/create-category", [OffenseController::class, "createCategory"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/edit-category/{id}", [OffenseController::class, "editCategory"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/create-subcategory", [OffenseController::class, "createSubCategory"]);
    Route::middleware(["permission:can_perform_offenses"])->post("/edit-subcategory/{id}", [OffenseController::class, "editSubCategory"]);
    //branches
    Route::get("/get-all-branches", [BranchController::class, "getAllBranches"]);
    Route::get("/get-branches", [BranchController::class, "getBranches"]);
    Route::middleware(["permission:can_perform_branches"])->post("/create-branch", [BranchController::class, "createBranch"]);
    Route::middleware(["permission:can_perform_branches"])->post("/edit-branch/{id}", [BranchController::class, "editBranch"]);
    //training titles
    Route::get("/get-all-training-titles", [TrainingTitleController::class, "getAllTrainingTitles"]);
    Route::get("/get-training-titles", [TrainingTitleController::class, "getTrainingTitles"]);
    Route::middleware(["permission:can_perform_training_titles"])->post("/create-training-title", [TrainingTitleController::class, "createTrainingTitle"]);
    Route::middleware(["permission:can_perform_training_titles"])->post("/edit-training-title/{id}", [TrainingTitleController::class, "editTrainingTitle"]);
    //diagnosis
    Route::get("/get-all-diagnosis", [DiagnosisController::class, "getAllDiagnosis"]);
    Route::get("/get-diagnosis", [DiagnosisController::class, "getDiagnosis"]);
    Route::middleware(["permission:can_perform_diagnosis"])->post("/create-diagnosis", [DiagnosisController::class, "createDiagnosis"]);
    Route::middleware(["permission:can_perform_diagnosis"])->post("/edit-diagnosis/{id}", [DiagnosisController::class, "editDiagnosis"]);
    //rate per group
    Route::get("/get-all-rates", [RateController::class, "getAllRates"]);
    Route::get("/get-rates", [RateController::class, "getRates"]);
    Route::middleware(["permission:can_perform_rate_per_group"])->post("/create-rate", [RateController::class, "createRate"]);
    Route::middleware(["permission:can_perform_rate_per_group"])->post("/edit-rate/{id}", [RateController::class, "editRate"]);
    //bank account types
    Route::get("/get-all-bank-account-types", [BankAccountTypeController::class, "getAllBankAccountTypes"]);
    Route::get("/get-bank-account-types", [BankAccountTypeController::class, "getBankAccountTypes"]);
    Route::middleware(["permission:can_perform_rate_per_group"])->post("/create-bank-account-type", [BankAccountTypeController::class, "createBankAccountType"]);
    Route::middleware(["permission:can_perform_rate_per_group"])->post("/edit-bank-account-type/{id}", [BankAccountTypeController::class, "editBankAccountType"]);
    //salary positions
    Route::get("/get-all-salary-positions", [SalaryPositionController::class, "getAllSalaryPositions"]);
    Route::get("/get-salary-positions", [SalaryPositionController::class, "getSalaryPositions"]);
    Route::middleware(["permission:can_perform_salary_positions"])->post("/create-salary-position", [SalaryPositionController::class, "createSalaryPosition"]);
    Route::middleware(["permission:can_perform_salary_positions"])->post("/edit-salary-position/{id}", [SalaryPositionController::class, "editSalaryPosition"]);

    // EMPLOYEES MODULE
    Route::get("/get-all-employees", [EmployeeController::class, "getAllEmployees"]);
    Route::get("/get-employees", [EmployeeController::class, "getEmployees"]);
    Route::get("/get-employee/{employee_number}", [EmployeeController::class, "getEmployee"]);
    Route::get("/get-employee-audit-logs/{employee_number}", [EmployeeController::class, "getEmployeeAuditLogs"]);
    Route::get("/get-employee-positions/{employee_number}", [EmployeeController::class, "getEmployeePositionHistory"]);
    Route::get("/get-employee-branches/{employee_number}", [EmployeeController::class, "getEmployeeBranchHistory"]);
    Route::get("/get-employee-contracts/{employee_number}", [EmployeeController::class, "getEmployeeContractHistory"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/add-employee", [EmployeeController::class, "addEmployee"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/update-employee/{employee_number}", [EmployeeController::class, "updateEmployeeDetails"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/add-employee-position/{empoyee_number}", [EmployeeController::class, "addPosition"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/edit-employee-position/{empoyee_number}", [EmployeeController::class, "editPosition"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/add-employee-branch/{empoyee_number}", [EmployeeController::class, "addBranch"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/edit-employee-branch/{empoyee_number}", [EmployeeController::class, "editBranch"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/add-employee-contract/{empoyee_number}", [EmployeeController::class, "addContract"]);
    Route::middleware(["permission:can_perform_employee_tracking"])->post("/edit-employee-contract/{empoyee_number}", [EmployeeController::class, "editContract"]);
});

