<?php

use App\Http\Controllers\Employees\ActingCapacityController;
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
use App\Http\Controllers\Employees\RequirementsAttachmentController;
use App\Http\Controllers\Maintenance\ScheduleTypeController;
use App\Http\Controllers\Employees\EmployeePromotionController;
use Illuminate\Support\Facades\Route;

// EMPLOYEES MODULE
Route::middleware('auth:sanctum')->group(function() {
    Route::get("/get-all-employees", [EmployeeController::class, "getAllEmployees"]);
    Route::get("/get-employees", [EmployeeController::class, "getEmployees"]);
    Route::get("/get-branch-employees", [EmployeeController::class, "getBranchEmployees"]);
    Route::get("/get-employees-list", [EmployeeController::class, "getEmployeeList"]);
    Route::get("/get-employee/{employee_number}", [EmployeeController::class, "getEmployee"]);
    Route::middleware(["permission:export_employee_tracking"])->get("/export-employees-list", [EmployeeController::class, "exportEmployee"]);
    Route::middleware(["permission:perform_employee_tracking"])->post("/add-employee", [EmployeeController::class, "addEmployee"]);
    Route::middleware(["permission:perform_employee_tracking"])->post("/update-employee/{employee_number}", [EmployeeController::class, "updateEmployeeDetails"]);
    //audit logs
    Route::get("/get-employee-audit-logs/{employee_number}", [EmployeeController::class, "getEmployeeAuditLogs"]);
    //positions history
    Route::get("/get-employee-positions/{employee_number}", [EmployeePositionControlller::class, "getEmployeePositionHistory"]);
    Route::get("/export-employee-positions/{employee_number}", [EmployeePositionControlller::class, "exportEmployeePositionHistory"]);
    Route::middleware(["permission:perform_employee_position"])->post("/add-employee-position/{employee_number}", [EmployeePositionControlller::class, "addPosition"]);
    Route::middleware(["permission:perform_employee_position"])->post("/edit-employee-position/{employee_number}", [EmployeePositionControlller::class, "editPosition"]);
    //branches history
    Route::get("/get-employee-branches/{employee_number}", [EmployeeBranchControlller::class, "getEmployeeBranchHistory"]);
    Route::get("/export-employee-branches/{employee_number}", [EmployeeBranchControlller::class, "exportEmployeeBranchHistory"]);
    Route::middleware(["permission:perform_employee_branches"])->post("/delete-employee-branches/{id}", [EmployeeBranchControlller::class, "deleteEmployeeBranch"]);
    Route::middleware(["permission:perform_employee_branches"])->post("/add-employee-branch/{employee_number}", [EmployeeBranchControlller::class, "addBranch"]);
    Route::middleware(["permission:perform_employee_branches"])->post("/edit-employee-branch/{employee_number}", [EmployeeBranchControlller::class, "editBranch"]);
    //contracts history
    Route::get("/get-employee-contracts/{employee_number}", [EmployeeContractControlller::class, "getEmployeeContractHistory"]);
    Route::get("/export-employee-contracts/{employee_number}", [EmployeeContractControlller::class, "exportEmployeeContractHistory"]);
    Route::middleware(["permission:perform_employee_contracts"])->post("/add-employee-contract/{employee_number}", [EmployeeContractControlller::class, "addContract"]);
    Route::middleware(["permission:perform_employee_contracts"])->post("/edit-employee-contract/{employee_number}", [EmployeeContractControlller::class, "editContract"]);
    Route::get("/get-employee-contract-details/{id}", [EmployeeContractControlller::class, "getContractDetails"]);
    Route::middleware(["permission:perform_employee_contracts"])->post('/employee/contracts/{id}/void', [EmployeeContractControlller::class, "voidEmployeeContract"]); 
    //bank details history
    Route::get("/get-employee-bank-details/{employee_number}", [EmployeeBankAccountController::class, "getEmployeeBankDetailsHistory"]);
    Route::middleware(["permission:perform_employee_bank_details"])->post("/add-employee-bank-details/{employee_number}", [EmployeeBankAccountController::class, "addBankDetails"]);
    Route::middleware(["permission:perform_employee_bank_details"])->post("/edit-employee-bank-details/{employee_number}", [EmployeeBankAccountController::class, "editBankDetails"]);
    //offenses history
    Route::get("/get-employee-offenses/{employee_number}", [EmployeeOffensesControlller::class, "getEmployeeOffenses"]);
    Route::get("/export-employee-offenses/{employee_number}", [EmployeeOffensesControlller::class, "exportEmployeeOffenses"]);
    Route::middleware(["permission:perform_employee_offenses"])->post("/add-employee-offense/{employee_number}", [EmployeeOffensesControlller::class, "addOffense"]);
    Route::middleware(["permission:perform_employee_offenses"])->post("/edit-employee-offense/{employee_number}", [EmployeeOffensesControlller::class, "editOffense"]);
    //requirements
    Route::get("/get-employee-requirements/{employee_number}", [EmployeeRequirementsController::class, "getEmployeeRequirements"]);
    Route::get("/export-employee-attachments/{employee_number}", [RequirementsAttachmentController::class, "exportRequirements"]);
    Route::middleware(["permission:perform_employee_requirements"])->post("/create-or-edit-employee-requirements/{employee_number}", [EmployeeRequirementsController::class, "updateOrCreateRequirements"]);
    Route::middleware(["permission:perform_employee_requirements"])->post("/upload-requirements-attachments/{employee_number}", [RequirementsAttachmentController::class, "uploadAttachments"]);
    Route::middleware(["permission:perform_employee_requirements"])->get("/get-requirements-attachments/{employee_number}", [RequirementsAttachmentController::class, "getAttachments"]);
    //trainings history
    Route::get("/get-employee-trainings/{employee_number}", [EmployeeTrainingController::class, "getEmployeeTrainings"]);
    Route::middleware(["permission:perform_employee_training_titles"])->post("/add-employee-training-title/{employee_number}", [EmployeeTrainingController::class, "addTrainingTitle"]);
    Route::middleware(["permission:perform_employee_training_titles"])->post("/edit-employee-training-title/{employee_number}", [EmployeeTrainingController::class, "editTrainingTitle"]);
    //vaccination
    Route::get("/get-employee-vaccination/{employee_number}", [EmployeeVaccinationController::class, "getEmployeeVaccines"]);
    Route::middleware(["permission:perform_employee_vaccination"])->post("/add-employee-vaccination/{employee_number}", [EmployeeVaccinationController::class, "addVaccine"]);
    Route::middleware(["permission:perform_employee_vaccination"])->post("/edit-employee-vaccination/{employee_number}", [EmployeeVaccinationController::class, "editVaccine"]);
    //health and safety
    Route::get("/get-employee-health-safety/{employee_number}", [EmployeeHealthSafetyController::class, "getEmployeeHealthSafety"]);
    Route::middleware(["permission:perform_employee_health_and_safety"])->post("/add-employee-health-safety/{employee_number}", [EmployeeHealthSafetyController::class, "addEmployeeHealthSafety"]);
    Route::middleware(["permission:perform_employee_health_and_safety"])->post("/edit-employee-health-safety/{employee_number}", [EmployeeHealthSafetyController::class, "editEmployeeHealthSafety"]);
    //acting capacity
    Route::get("/get-all-acting-capacity", [ActingCapacityController::class, "getAllActingCapacity"]);
    Route::get("/export-all-acting-capacity", [ActingCapacityController::class, "exportAllActingCapacity"]);
    Route::get("/get-acting-capacity/{id}", [ActingCapacityController::class, "getActingCapacity"]);
    Route::get("/get-acf/{id}", [ActingCapacityController::class, "getACFDetails"]);
    Route::get("/get-promotion-summary/{id}", [EmployeePromotionController::class, "getPromotionSummary"]); 
    Route::middleware(["permission:perform_acting_capacity"])->post("/add-acting-capacity", [ActingCapacityController::class, "addNewActingCapacity"]);
    Route::middleware(["permission:perform_acting_capacity"])->post("/edit-acting-capacity", [ActingCapacityController::class, "editActingCapacity"]);
    Route::middleware(["permission:generate_acting_capacity"])->post("/generate-confirmation-promotion/{id}", [ActingCapacityController::class, "generateConfirmationDocuments"]);
    Route::middleware(["permission:reject_acting_capacity"])->post("/reject-promotion/{id}", [ActingCapacityController::class, "rejectPromotion"]);
    Route::middleware(["permission:approve_acting_capacity"])->post("/confirm-promotion/{id}", [ActingCapacityController::class, "confirmPromotion"]);
    //schedule type
    Route::get("/get-all-schedule-type", [ScheduleTypeController::class, "getAllScheduleTypes"]);
    Route::get("/get-schedule-type", [ScheduleTypeController::class, "getScheduleTypes"]);
    Route::middleware(["permission:perform_schedule_type"])->post("/add-schedule-type", [ScheduleTypeController::class, "createScheduleType"]);
    Route::middleware(["permission:perform_schedule_type"])->post("/edit-schedule-type/{id}", [ScheduleTypeController::class, "editScheduleType"]);

    //employees without payroll list relationship
    Route::get("/get-employees-without-payroll-list", [EmployeeController::class, "getEmployeesWithoutPayrollList"]);
});