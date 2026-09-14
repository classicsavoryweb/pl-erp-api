<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //administrator
        Permission::updateOrCreate(["name" => "view_administrators_module"], ["module" => "administrators", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_roles_and_permissions"], ["module" => "administrators", "submodule" => "roles_and_permissions"]);
        Permission::updateOrCreate(["name" => "perform_roles_and_permissions"], ["module" => "administrators", "submodule" => "roles_and_permissions"]);
        Permission::updateOrCreate(["name" => "view_users"], ["module" => "administrators", "submodule" => "users"]);
        Permission::updateOrCreate(["name" => "perform_users"], ["module" => "administrators", "submodule" => "users"]);
        Permission::updateOrCreate(["name" => "view_activity_logs"], ["module" => "administrators", "submodule" => "activity_logs"]);
        Permission::updateOrCreate(["name" => "perform_database_backups"], ["module" => "administrators", "submodule" => "database_backups"]);
        Permission::updateOrCreate(["name" => "view_database_backups"], ["module" => "administrators", "submodule" => "database_backups"]);
        Permission::updateOrCreate(["name" => "view_tardiness_disabler"], ["module" => "administrators", "submodule" => "tardiness_disabler"]);
        Permission::updateOrCreate(["name" => "has_general_dashboard"], ["module" => "administrators", "submodule" => "dashboard"]);
        Permission::updateOrCreate(["name" => "has_analytic_dashboard_hr"], ["module" => "administrators", "submodule" => "dashboard"]);
        Permission::updateOrCreate(["name" => "has_analytic_dashboard_payroll"], ["module" => "administrators", "submodule" => "dashboard"]);
        Permission::updateOrCreate(["name" => "has_uniform_dashboard"], ["module" => "administrators", "submodule" => "dashboard"]);
        // Permission::updateOrCreate(["name" => "by_pass_edit_restrictions"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "by_pass_edit_restrictions_employees"], ["module" => "administrators", "submodule" => "edit_restrictions"]);
        Permission::updateOrCreate(["name" => "by_pass_edit_restrictions_time_keeping"], ["module" => "administrators", "submodule" => "edit_restrictions"]);
        Permission::updateOrCreate(["name" => "by_pass_edit_restrictions_payroll"], ["module" => "administrators", "submodule" => "edit_restrictions"]);
        Permission::updateOrCreate(["name" => "view_scheduled_timekeeping"], ["module" => "administrators", "submodule" => "scheduled_timekeeping"]);
        Permission::updateOrCreate(["name" => "perform_scheduled_timekeeping"], ["module" => "administrators", "submodule" => "scheduled_timekeeping"]);
        // Permission::updateOrCreate(["name" => "view_settings"]);
        // Permission::updateOrCreate(["name" => "perform_settings"]);

        //maintenance module
        Permission::updateOrCreate(["name" => "view_maintenance_module"], ["module" => "maintenance", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_positions"], ["module" => "maintenance", "submodule" => "positions"]);
        Permission::updateOrCreate(["name" => "perform_positions"], ["module" => "maintenance", "submodule" => "positions"]);
        Permission::updateOrCreate(["name" => "view_branches"], ["module" => "maintenance", "submodule" => "branches"]);
        Permission::updateOrCreate(["name" => "view_region"], ["module" => "maintenance", "submodule" => "region"]);
        $legacyPerformBranches = Permission::where("name", "perform_branches")->first();
        $createBranches = Permission::updateOrCreate(["name" => "create_branches"], ["module" => "maintenance", "submodule" => "branches"]);
        if ($legacyPerformBranches) {
            foreach ($legacyPerformBranches->roles as $role) {
                $role->givePermissionTo($createBranches);
            }
            $legacyPerformBranches->delete();
        }
        Permission::where("name", "edit_branches")->delete();
        Permission::updateOrCreate(["name" => "perform_region"], ["module" => "maintenance", "submodule" => "region"]);
        Permission::updateOrCreate(["name" => "edit_branches_all"], ["module" => "maintenance", "submodule" => "branches"]);
        Permission::updateOrCreate(["name" => "edit_branches_oic"], ["module" => "maintenance", "submodule" => "branches"]);
        Permission::updateOrCreate(["name" => "view_employee_status"], ["module" => "maintenance", "submodule" => "employee_status"]);
        Permission::updateOrCreate(["name" => "perform_employee_status"], ["module" => "maintenance", "submodule" => "employee_status"]);
        Permission::updateOrCreate(["name" => "view_stations"], ["module" => "maintenance", "submodule" => "stations"]);
        Permission::updateOrCreate(["name" => "perform_stations"], ["module" => "maintenance", "submodule" => "stations"]);
        Permission::updateOrCreate(["name" => "view_agencies"], ["module" => "maintenance", "submodule" => "agencies"]);
        Permission::updateOrCreate(["name" => "perform_agencies"], ["module" => "maintenance", "submodule" => "agencies"]);
        Permission::updateOrCreate(["name" => "view_offenses"], ["module" => "maintenance", "submodule" => "offenses"]);
        Permission::updateOrCreate(["name" => "perform_offenses"], ["module" => "maintenance", "submodule" => "offenses"]);
        Permission::updateOrCreate(["name" => "view_training_titles"], ["module" => "maintenance", "submodule" => "training_titles"]);
        Permission::updateOrCreate(["name" => "perform_training_titles"], ["module" => "maintenance", "submodule" => "training_titles"]);
        Permission::updateOrCreate(["name" => "view_diagnosis"], ["module" => "maintenance", "submodule" => "diagnosis"]);
        Permission::updateOrCreate(["name" => "perform_diagnosis"], ["module" => "maintenance", "submodule" => "diagnosis"]);
        Permission::updateOrCreate(["name" => "view_rate_per_group"], ["module" => "maintenance", "submodule" => "rate_per_group"]);
        Permission::updateOrCreate(["name" => "perform_rate_per_group"], ["module" => "maintenance", "submodule" => "rate_per_group"]);
        Permission::updateOrCreate(["name" => "view_salary_positions"], ["module" => "maintenance", "submodule" => "salary_positions"]);
        Permission::updateOrCreate(["name" => "perform_salary_positions"], ["module" => "maintenance", "submodule" => "salary_positions"]);
        Permission::updateOrCreate(["name" => "view_bank_account_types"], ["module" => "maintenance", "submodule" => "bank_account_types"]);
        Permission::updateOrCreate(["name" => "perform_bank_account_types"], ["module" => "maintenance", "submodule" => "bank_account_types"]);
        Permission::updateOrCreate(["name" => "view_vaccine_brand"], ["module" => "maintenance", "submodule" => "vaccine_brand"]);
        Permission::updateOrCreate(["name" => "perform_vaccine_brand"], ["module" => "maintenance", "submodule" => "vaccine_brand"]);
        Permission::updateOrCreate(["name" => "view_coe_reasons"], ["module" => "maintenance", "submodule" => "coe_reasons"]);
        Permission::updateOrCreate(["name" => "perform_coe_reasons"], ["module" => "maintenance", "submodule" => "coe_reasons"]);
        Permission::updateOrCreate(["name" => "view_acting_capacity_reasons"], ["module" => "maintenance", "submodule" => "acting_capacity_reasons"]);
        Permission::updateOrCreate(["name" => "perform_acting_capacity_reasons"], ["module" => "maintenance", "submodule" => "acting_capacity_reasons"]);
        Permission::updateOrCreate(["name" => "view_sanctions"], ["module" => "maintenance", "submodule" => "sanctions"]);
        Permission::updateOrCreate(["name" => "perform_sanctions"], ["module" => "maintenance", "submodule" => "sanctions"]);
        Permission::updateOrCreate(["name" => "view_schedule_type"], ["module" => "maintenance", "submodule" => "schedule_type"]);
        Permission::updateOrCreate(["name" => "perform_schedule_type"], ["module" => "maintenance", "submodule" => "schedule_type"]);
        Permission::updateOrCreate(["name" => "view_schedule_masterlist"], ["module" => "maintenance", "submodule" => "schedule_masterlist"]);
        Permission::updateOrCreate(["name" => "perform_schedule_masterlist"], ["module" => "maintenance", "submodule" => "schedule_masterlist"]);
        Permission::updateOrCreate(["name" => "view_holidays"], ["module" => "maintenance", "submodule" => "holidays"]);
        Permission::updateOrCreate(["name" => "perform_holidays"], ["module" => "maintenance", "submodule" => "holidays"]);
        Permission::updateOrCreate(["name" => "view_sss"], ["module" => "maintenance", "submodule" => "sss"]);
        Permission::updateOrCreate(["name" => "perform_sss"], ["module" => "maintenance", "submodule" => "sss"]);
        Permission::updateOrCreate(["name" => "view_tax"], ["module" => "maintenance", "submodule" => "tax"]);
        Permission::updateOrCreate(["name" => "perform_tax"], ["module" => "maintenance", "submodule" => "tax"]);
        Permission::updateOrCreate(["name" => "view_philhealth"], ["module" => "maintenance", "submodule" => "philhealth"]);
        Permission::updateOrCreate(["name" => "perform_philhealth"], ["module" => "maintenance", "submodule" => "philhealth"]);
        Permission::updateOrCreate(["name" => "view_pagibig"], ["module" => "maintenance", "submodule" => "pagibig"]);
        Permission::updateOrCreate(["name" => "perform_pagibig"], ["module" => "maintenance", "submodule" => "pagibig"]);
        Permission::updateOrCreate(["name" => "view_deductions"], ["module" => "maintenance", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "perform_deductions"], ["module" => "maintenance", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "view_additional_income"], ["module" => "maintenance", "submodule" => "additional_income"]);
        Permission::updateOrCreate(["name" => "perform_additional_income"], ["module" => "maintenance", "submodule" => "additional_income"]);
        Permission::updateOrCreate(["name" => "view_period_definition"], ["module" => "maintenance", "submodule" => "period_definition"]);
        Permission::updateOrCreate(["name" => "perform_period_definition"], ["module" => "maintenance", "submodule" => "period_definition"]);
        Permission::updateOrCreate(["name" => "view_cshub_transaction_type"], ["module" => "maintenance", "submodule" => "cshub_transaction_type"]);
        Permission::updateOrCreate(["name" => "perform_cshub_transaction_type"], ["module" => "maintenance", "submodule" => "cshub_transaction_type"]);
        Permission::updateOrCreate(["name" => "view_atd_access_type"], ["module" => "maintenance", "submodule" => "atd_access_type"]);
        Permission::updateOrCreate(["name" => "view_adjustment_types"], ["module" => "maintenance", "submodule" => "adjustment_types"]);
        Permission::updateOrCreate(["name" => "perform_adjustment_types"], ["module" => "maintenance", "submodule" => "adjustment_types"]);

        Permission::updateOrCreate(["name" => "view_contributions_and_tax"], ["module" => "maintenance", "submodule" => "contributions_and_tax"]);
        Permission::updateOrCreate(["name" => "perform_contributions_and_tax"], ["module" => "maintenance", "submodule" => "contributions_and_tax"]);

        Permission::updateOrCreate(["name" => "view_terms"], ["module" => "maintenance", "submodule" => "terms"]);
        Permission::updateOrCreate(["name" => "perform_terms"], ["module" => "maintenance", "submodule" => "terms"]);
        Permission::updateOrCreate(["name" => "view_last_pay_setup"], ["module" => "maintenance", "submodule" => "last_pay_setup"]);
        Permission::updateOrCreate(["name" => "perform_last_pay_setup"], ["module" => "maintenance", "submodule" => "last_pay_setup"]);
        Permission::updateOrCreate(["name" => "view_tardiness_sanctions"], ["module" => "maintenance", "submodule" => "tardiness_sanctions"]);
        Permission::updateOrCreate(["name" => "perform_tardiness_sanctions"], ["module" => "maintenance", "submodule" => "tardiness_sanctions"]);



        //employees module

        Permission::updateOrCreate(["name" => "view_employees_module"], ["module" => "employees", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_employee_tracking"], ["module" => "employees", "submodule" => "employee_tracking"]);
        Permission::updateOrCreate(["name" => "perform_employee_tracking"], ["module" => "employees", "submodule" => "employee_tracking"]);
        Permission::updateOrCreate(["name" => "export_employee_tracking"], ["module" => "employees", "submodule" => "employee_tracking"]);
        Permission::updateOrCreate(["name" => "view_acting_capacity"], ["module" => "employees", "submodule" => "acting_capacity"]);
        Permission::updateOrCreate(["name" => "export_acting_capacity"], ["module" => "employees", "submodule" => "acting_capacity"]);
        Permission::updateOrCreate(["name" => "perform_acting_capacity"], ["module" => "employees", "submodule" => "acting_capacity"]);
        Permission::updateOrCreate(["name" => "generate_acting_capacity"], ["module" => "employees", "submodule" => "acting_capacity"]);
        Permission::updateOrCreate(["name" => "approve_acting_capacity"], ["module" => "employees", "submodule" => "acting_capacity"]);
        Permission::updateOrCreate(["name" => "reject_acting_capacity"], ["module" => "employees", "submodule" => "acting_capacity"]);

        Permission::updateOrCreate(["name" => "view_employee_branches"], ["module" => "employees", "submodule" => "employee_branches"]);
        Permission::updateOrCreate(["name" => "perform_employee_branches"], ["module" => "employees", "submodule" => "employee_branches"]);
        Permission::updateOrCreate(["name" => "view_employee_offenses"], ["module" => "employees", "submodule" => "employee_offenses"]);
        Permission::updateOrCreate(["name" => "perform_employee_offenses"], ["module" => "employees", "submodule" => "employee_offenses"]);
        Permission::updateOrCreate(["name" => "view_employee_position"], ["module" => "employees", "submodule" => "employee_position"]);
        Permission::updateOrCreate(["name" => "view_promotion_history"], ["module" => "employees", "submodule" => "employee_position"]);
        Permission::updateOrCreate(["name" => "perform_employee_position"], ["module" => "employees", "submodule" => "employee_position"]);
        Permission::updateOrCreate(["name" => "view_employee_contracts"], ["module" => "employees", "submodule" => "employee_contracts"]);
        Permission::updateOrCreate(["name" => "perform_employee_contracts"], ["module" => "employees", "submodule" => "employee_contracts"]);
        Permission::updateOrCreate(["name" => "view_employee_requirements"], ["module" => "employees", "submodule" => "employee_requirements"]);
        Permission::updateOrCreate(["name" => "perform_employee_requirements"], ["module" => "employees", "submodule" => "employee_requirements"]);
        Permission::updateOrCreate(["name" => "view_employee_bank_details"], ["module" => "employees", "submodule" => "employee_bank_details"]);
        Permission::updateOrCreate(["name" => "perform_employee_bank_details"], ["module" => "employees", "submodule" => "employee_bank_details"]);
        Permission::updateOrCreate(["name" => "view_employee_training_titles"], ["module" => "employees", "submodule" => "employee_training_titles"]);
        Permission::updateOrCreate(["name" => "perform_employee_training_titles"], ["module" => "employees", "submodule" => "employee_training_titles"]);
        Permission::updateOrCreate(["name" => "view_employee_vaccination"], ["module" => "employees", "submodule" => "employee_vaccination"]);
        Permission::updateOrCreate(["name" => "perform_employee_vaccination"], ["module" => "employees", "submodule" => "employee_vaccination"]);
        Permission::updateOrCreate(["name" => "view_employee_audit_logs"], ["module" => "employees", "submodule" => "employee_audit_logs"]);

        //all records module
        // Permission::updateOrCreate(["name" => "view_all_records_health_and_safety"]);
        // Permission::updateOrCreate(["name" => "perform_all_records_health_and_safety"]);
        Permission::updateOrCreate(["name" => "view_all_records_module"], ["module" => "all_records", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_all_records_offenses"], ["module" => "all_records", "submodule" => "offenses"]);
        Permission::updateOrCreate(["name" => "perform_all_records_offenses"], ["module" => "all_records", "submodule" => "offenses"]);
        Permission::updateOrCreate(["name" => "export_all_records_offenses"], ["module" => "all_records", "submodule" => "offenses"]);
        Permission::updateOrCreate(["name" => "view_all_records_vaccination"], ["module" => "all_records", "submodule" => "vaccination"]);
        Permission::updateOrCreate(["name" => "perform_all_records_vaccination"], ["module" => "all_records", "submodule" => "vaccination"]);
        Permission::updateOrCreate(["name" => "view_all_records_coe_requests"], ["module" => "all_records", "submodule" => "coe_requests"]);
        Permission::updateOrCreate(["name" => "perform_all_records_coe_requests"], ["module" => "all_records", "submodule" => "coe_requests"]);
        Permission::updateOrCreate(["name" => "cancel_all_records_coe_requests"], ["module" => "all_records", "submodule" => "coe_requests"]);
        Permission::updateOrCreate(["name" => "approve_all_records_coe_requests"], ["module" => "all_records", "submodule" => "coe_requests"]);
        Permission::updateOrCreate(["name" => "view_all_records_employment_status"], ["module" => "all_records", "submodule" => "employment_status"]);
        Permission::updateOrCreate(["name" => "perform_all_records_employment_status"], ["module" => "all_records", "submodule" => "employment_status"]);
        Permission::updateOrCreate(["name" => "view_all_records_tardiness"], ["module" => "all_records", "submodule" => "tardiness"]);
        Permission::updateOrCreate(["name" => "perform_all_records_tardiness"], ["module" => "all_records", "submodule" => "tardiness"]);
        Permission::updateOrCreate(["name" => "import_all_records_tardiness"], ["module" => "all_records", "submodule" => "tardiness"]);
        Permission::updateOrCreate(["name" => "approve_all_records_tardiness"], ["module" => "all_records", "submodule" => "tardiness"]);
        Permission::updateOrCreate(["name" => "void_all_records_tardiness"], ["module" => "all_records", "submodule" => "tardiness"]);

        //transactions module
        Permission::updateOrCreate(["name" => "view_transactions_module"], ["module" => "transactions", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_transactions_schedule_time"], ["module" => "transactions", "submodule" => "schedule_time"]);
        Permission::updateOrCreate(["name" => "perform_transactions_schedule_time"], ["module" => "transactions", "submodule" => "schedule_time"]);
        Permission::updateOrCreate(["name" => "transfer_time_keeping_branch"], ["module" => "transactions", "submodule" => "schedule_time"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_schedule_time"], ["module" => "transactions", "submodule" => "schedule_time"]);
        Permission::updateOrCreate(["name" => "view_transactions_actual_time"], ["module" => "transactions", "submodule" => "actual_time"]);
        Permission::updateOrCreate(["name" => "perform_transactions_actual_time"], ["module" => "transactions", "submodule" => "actual_time"]);
        Permission::updateOrCreate(["name" => "import_transactions_actual_time"], ["module" => "transactions", "submodule" => "actual_time"]);
        Permission::updateOrCreate(["name" => "import_all_transactions_schedule_time"], ["module" => "transactions", "submodule" => "schedule_time"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_actual_logs"], ["module" => "transactions", "submodule" => "actual_time"]);
        Permission::updateOrCreate(["name" => "view_transactions_time_keeping"], ["module" => "transactions", "submodule" => "time_keeping"]);
        Permission::updateOrCreate(["name" => "perform_transactions_time_keeping"], ["module" => "transactions", "submodule" => "time_keeping"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_time_keeping"], ["module" => "transactions", "submodule" => "time_keeping"]);
        Permission::updateOrCreate(["name" => "view_transactions_time_keeping_summary"], ["module" => "transactions", "submodule" => "time_keeping_summary"]);
        Permission::updateOrCreate(["name" => "perform_transactions_time_keeping_summary"], ["module" => "transactions", "submodule" => "time_keeping_summary"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_time_keeping_summary"], ["module" => "transactions", "submodule" => "time_keeping_summary"]);
        Permission::updateOrCreate(["name" => "view_transactions_adjustments"], ["module" => "transactions", "submodule" => "adjustments"]);
        Permission::updateOrCreate(["name" => "perform_transactions_adjustments"], ["module" => "transactions", "submodule" => "adjustments"]);
        Permission::updateOrCreate(["name" => "approve_transactions_adjustments"], ["module" => "transactions", "submodule" => "adjustments"]);
        Permission::updateOrCreate(["name" => "unapprove_transactions_adjustments"], ["module" => "transactions", "submodule" => "adjustments"]);
        Permission::updateOrCreate(["name" => "view_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "perform_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "import_all_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "approve_all_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "unapprove_all_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "void_all_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "force_adjust_all_transactions_overtime"], ["module" => "transactions", "submodule" => "overtime"]);
        Permission::updateOrCreate(["name" => "view_transactions_travel_overtime"], ["module" => "transactions", "submodule" => "travel_overtime"]);
        Permission::updateOrCreate(["name" => "perform_transactions_travel_overtime"], ["module" => "transactions", "submodule" => "travel_overtime"]);

        //compensation modules
        Permission::updateOrCreate(["name" => "view_compensation_module"], ["module" => "compensation", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_compensation_payroll_list"], ["module" => "compensation", "submodule" => "payroll_list"]);
        Permission::updateOrCreate(["name" => "perform_compensation_payroll_list"], ["module" => "compensation", "submodule" => "payroll_list"]);
        Permission::updateOrCreate(["name" => "perform_compensation_payroll_list_salary"], ["module" => "compensation", "submodule" => "payroll_list"]);
        Permission::updateOrCreate(["name" => "view_compensation_cutoff_setup"], ["module" => "compensation", "submodule" => "cutoff_setup"]);
        Permission::updateOrCreate(["name" => "perform_compensation_cutoff_setup"], ["module" => "compensation", "submodule" => "cutoff_setup"]);
        Permission::updateOrCreate(["name" => "initialize_compensation_cutoff_setup"], ["module" => "compensation", "submodule" => "cutoff_setup"]);
        Permission::updateOrCreate(["name" => "generate_payroll_compensation_cutoff_setup"], ["module" => "compensation", "submodule" => "cutoff_setup"]);
        Permission::updateOrCreate(["name" => "finalize_compensation_cutoff_setup"], ["module" => "compensation", "submodule" => "cutoff_setup"]);
        Permission::updateOrCreate(["name" => "recompute_incentives_compensation_cutoff_setup"], ["module" => "compensation", "submodule" => "cutoff_setup"]);

        Permission::updateOrCreate(["name" => "view_compensation_deductions"], ["module" => "compensation", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "perform_compensation_deductions"], ["module" => "compensation", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "end_loan_compensation_deductions"], ["module" => "compensation", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "edit_compensation_deductions"], ["module" => "compensation", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "export_all_compensation_deductions"], ["module" => "compensation", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "view_compensation_payroll"], ["module" => "compensation", "submodule" => "payroll"]);
        Permission::updateOrCreate(["name" => "perform_compensation_payroll"], ["module" => "compensation", "submodule" => "payroll"]);
        Permission::updateOrCreate(["name" => "view_compensation_incentives"], ["module" => "compensation", "submodule" => "incentives"]);
        Permission::updateOrCreate(["name" => "perform_compensation_incentives"], ["module" => "compensation", "submodule" => "incentives"]);
        Permission::updateOrCreate(["name" => "view_compensation_entries"], ["module" => "compensation", "submodule" => "entries"]);
        Permission::updateOrCreate(["name" => "perform_compensation_entries"], ["module" => "compensation", "submodule" => "entries"]);
        Permission::updateOrCreate(["name" => "export_all_compensation_entries"], ["module" => "compensation", "submodule" => "entries"]);
        Permission::updateOrCreate(["name" => "view_compensation_converter"], ["module" => "compensation", "submodule" => "converter"]);
        Permission::updateOrCreate(["name" => "perform_compensation_converter"], ["module" => "compensation", "submodule" => "converter"]);
        Permission::updateOrCreate(["name" => "view_compensation_payroll_prooflist"], ["module" => "compensation", "submodule" => "payroll_prooflist"]);
        Permission::updateOrCreate(["name" => "perform_compensation_payroll_prooflist"], ["module" => "compensation", "submodule" => "payroll_prooflist"]);
        Permission::updateOrCreate(["name" => "view_compensation_last_pay"], ["module" => "compensation", "submodule" => "last_pay"]);
        Permission::updateOrCreate(["name" => "perform_compensation_last_pay"], ["module" => "compensation", "submodule" => "last_pay"]);
        Permission::updateOrCreate(["name" => "view_compensation_additional_income"], ["module" => "compensation", "submodule" => "additional_income"]);
        Permission::updateOrCreate(["name" => "perform_compensation_additional_income"], ["module" => "compensation", "submodule" => "additional_income"]);
        Permission::updateOrCreate(["name" => "view_compensation_atd"], ["module" => "compensation", "submodule" => "atd"]);
        Permission::updateOrCreate(["name" => "perform_compensation_atd"], ["module" => "compensation", "submodule" => "atd"]);
        Permission::updateOrCreate(["name" => "export_compensation_atd"], ["module" => "compensation", "submodule" => "atd"]);
        Permission::updateOrCreate(["name" => "view_incentives_management"], ["module" => "compensation", "submodule" => "incentives_management"]);
        Permission::updateOrCreate(["name" => "perform_incentives_management"], ["module" => "compensation", "submodule" => "incentives_management"]);
        Permission::updateOrCreate(["name" => "view_incentives_bracket"], ["module" => "compensation", "submodule" => "incentives_bracket"]);
        Permission::updateOrCreate(["name" => "perform_incentives_bracket"], ["module" => "compensation", "submodule" => "incentives_bracket"]);
        Permission::updateOrCreate(["name" => "view_incentives_per_employee"], ["module" => "compensation", "submodule" => "incentives_per_employee"]);
        Permission::updateOrCreate(["name" => "perform_incentives_per_employee"], ["module" => "compensation", "submodule" => "incentives_per_employee"]);
        Permission::updateOrCreate(["name" => "view_incentives_position"], ["module" => "compensation", "submodule" => "incentives_position"]);
        Permission::updateOrCreate(["name" => "perform_incentives_position"], ["module" => "compensation", "submodule" => "incentives_position"]);

        //reports modules
        Permission::updateOrCreate(["name" => "view_reports_module"], ["module" => "reports", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_statutory_reports"], ["module" => "reports", "submodule" => "statutory_reports"]);
        Permission::updateOrCreate(["name" => "view_reports_pagibig"], ["module" => "reports", "submodule" => "statutory_reports"]);
        Permission::updateOrCreate(["name" => "view_reports_philhealth"], ["module" => "reports", "submodule" => "statutory_reports"]);
        Permission::updateOrCreate(["name" => "view_reports_sss"], ["module" => "reports", "submodule" => "statutory_reports"]);
        Permission::updateOrCreate(["name" => "view_reports_bdo_cash_card"], ["module" => "reports", "submodule" => "bdo_cash_card"]);
        Permission::updateOrCreate(["name" => "view_reports_13th_month_pay"], ["module" => "reports", "submodule" => "13th_month_pay"]);
        Permission::updateOrCreate(["name" => "perform_reports_13th_month_pay_entries"], ["module" => "reports", "submodule" => "13th_month_pay"]);
        Permission::updateOrCreate(["name" => "view_reports_pay_slip"], ["module" => "reports", "submodule" => "pay_slip"]);
        Permission::updateOrCreate(["name" => "perform_reports_pay_slip"], ["module" => "reports", "submodule" => "pay_slip"]);
        Permission::updateOrCreate(["name" => "generate_13th_month_pay"], ["module" => "reports", "submodule" => "13th_month_pay"]);
        Permission::updateOrCreate(["name" => "view_reports_bir"], ["module" => "reports", "submodule" => "bir"]);
        Permission::updateOrCreate(["name" => "perform_reports_bir"], ["module" => "reports", "submodule" => "bir"]);
        Permission::updateOrCreate(["name" => "view_reports_bir_1601c"], ["module" => "reports", "submodule" => "bir_1601c"]);
        Permission::updateOrCreate(["name" => "perform_reports_bir_1601c"], ["module" => "reports", "submodule" => "bir_1601c"]);
        Permission::updateOrCreate(["name" => "view_reports_bir_2316"], ["module" => "reports", "submodule" => "bir_2316"]);
        Permission::updateOrCreate(["name" => "perform_reports_bir_2316"], ["module" => "reports", "submodule" => "bir_2316"]);
        Permission::updateOrCreate(["name" => "view_reports_deductions"], ["module" => "reports", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "perform_reports_deductions"], ["module" => "reports", "submodule" => "deductions"]);
        Permission::updateOrCreate(["name" => "view_reports_incentive_attendance"], ["module" => "reports", "submodule" => "incentive_attendance"]);
        Permission::updateOrCreate(["name" => "perform_reports_incentive_attendance"], ["module" => "reports", "submodule" => "incentive_attendance"]);
        Permission::updateOrCreate(["name" => "view_reports_incentive_report"], ["module" => "reports", "submodule" => "incentive_report"]);
        Permission::updateOrCreate(["name" => "perform_reports_incentive_report"], ["module" => "reports", "submodule" => "incentive_report"]);
        Permission::updateOrCreate(["name" => "view_reports_timekeeping_summary_report"], ["module" => "reports", "submodule" => "timekeeping_summary_report"]);
        Permission::updateOrCreate(["name" => "perform_reports_timekeeping_summary_report"], ["module" => "reports", "submodule" => "timekeeping_summary_report"]);
        Permission::updateOrCreate(["name" => "view_reports_bank_account_history"], ["module" => "reports", "submodule" => "bank_account_history"]);
        Permission::updateOrCreate(["name" => "perform_reports_bank_account_history"], ["module" => "reports", "submodule" => "bank_account_history"]);


        //uniform (maintenance)
        Permission::updateOrCreate(["name" => "view_maintenance_suppliers"], ["module" => "maintenance", "submodule" => "suppliers"]);
        Permission::updateOrCreate(["name" => "perform_maintenance_suppliers"], ["module" => "maintenance", "submodule" => "suppliers"]);
        Permission::updateOrCreate(["name" => "view_maintenance_sizes"], ["module" => "maintenance", "submodule" => "sizes"]);
        Permission::updateOrCreate(["name" => "perform_maintenance_sizes"], ["module" => "maintenance", "submodule" => "sizes"]);
        //uniform module (section)
        Permission::updateOrCreate(["name" => "view_uniform_module"], ["module" => "uniform", "submodule" => "module_access"]);
        Permission::updateOrCreate(["name" => "view_uniform_item_masterlist"], ["module" => "uniform", "submodule" => "item_masterlist"]);
        Permission::updateOrCreate(["name" => "perform_uniform_item_masterlist"], ["module" => "uniform", "submodule" => "item_masterlist"]);
        Permission::updateOrCreate(["name" => "perform_uniform_item_masterlist_create"], ["module" => "uniform", "submodule" => "item_masterlist"]);
        Permission::updateOrCreate(["name" => "perform_uniform_item_masterlist_edit"], ["module" => "uniform", "submodule" => "item_masterlist"]);
        Permission::updateOrCreate(["name" => "perform_uniform_item_masterlist_export"], ["module" => "uniform", "submodule" => "item_masterlist"]);
        Permission::updateOrCreate(["name" => "view_uniform_request_and_issuance"], ["module" => "uniform", "submodule" => "request_and_issuance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_request_and_issuance"], ["module" => "uniform", "submodule" => "request_and_issuance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_request_and_issuance_create"], ["module" => "uniform", "submodule" => "request_and_issuance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_request_and_issuance_export"], ["module" => "uniform", "submodule" => "request_and_issuance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_request_and_issuance_cancel"], ["module" => "uniform", "submodule" => "request_and_issuance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_request_and_issuance_review"], ["module" => "uniform", "submodule" => "request_and_issuance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_request_and_issuance_post"], ["module" => "uniform", "submodule" => "request_and_issuance"]);
        Permission::updateOrCreate(["name" => "view_uniform_replenishment_request"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request_create"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request_export"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request_review"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request_post"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request_cancel"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request_close"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request_replace_supplier"], ["module" => "uniform", "submodule" => "replenishment_request"]);
        Permission::updateOrCreate(["name" => "view_uniform_receiving_stocks"], ["module" => "uniform", "submodule" => "receiving_stocks"]);
        Permission::updateOrCreate(["name" => "perform_uniform_receiving_stocks"], ["module" => "uniform", "submodule" => "receiving_stocks"]);
        Permission::updateOrCreate(["name" => "perform_uniform_receiving_stocks_create"], ["module" => "uniform", "submodule" => "receiving_stocks"]);
        Permission::updateOrCreate(["name" => "perform_uniform_receiving_stocks_export"], ["module" => "uniform", "submodule" => "receiving_stocks"]);
        Permission::updateOrCreate(["name" => "perform_uniform_receiving_stocks_cancel"], ["module" => "uniform", "submodule" => "receiving_stocks"]);
        Permission::updateOrCreate(["name" => "perform_uniform_receiving_stocks_review"], ["module" => "uniform", "submodule" => "receiving_stocks"]);
        Permission::updateOrCreate(["name" => "perform_uniform_receiving_stocks_post"], ["module" => "uniform", "submodule" => "receiving_stocks"]);
        Permission::updateOrCreate(["name" => "view_uniform_weighted_average"], ["module" => "uniform", "submodule" => "weighted_average"]);
        Permission::updateOrCreate(["name" => "perform_uniform_weighted_average"], ["module" => "uniform", "submodule" => "weighted_average"]);
        Permission::updateOrCreate(["name" => "perform_uniform_weighted_average_create"], ["module" => "uniform", "submodule" => "weighted_average"]);
        Permission::updateOrCreate(["name" => "perform_uniform_weighted_average_export"], ["module" => "uniform", "submodule" => "weighted_average"]);
        Permission::updateOrCreate(["name" => "perform_uniform_weighted_average_cancel"], ["module" => "uniform", "submodule" => "weighted_average"]);
        Permission::updateOrCreate(["name" => "perform_uniform_weighted_average_update_status"], ["module" => "uniform", "submodule" => "weighted_average"]);
        Permission::updateOrCreate(["name" => "perform_uniform_weighted_average_update_price"], ["module" => "uniform", "submodule" => "weighted_average"]);
        Permission::updateOrCreate(["name" => "view_uniform_physical_count"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count_create"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count_export"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count_cancel"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count_update_quantity"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count_review"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count_post"], ["module" => "uniform", "submodule" => "physical_count"]);
        Permission::updateOrCreate(["name" => "view_uniform_variance_report"], ["module" => "uniform", "submodule" => "variance_report"]);
        Permission::updateOrCreate(["name" => "perform_uniform_variance_report"], ["module" => "uniform", "submodule" => "variance_report"]);
        Permission::updateOrCreate(["name" => "perform_uniform_variance_report_create"], ["module" => "uniform", "submodule" => "variance_report"]);
        Permission::updateOrCreate(["name" => "perform_uniform_variance_report_export"], ["module" => "uniform", "submodule" => "variance_report"]);
        Permission::updateOrCreate(["name" => "perform_uniform_variance_report_cancel"], ["module" => "uniform", "submodule" => "variance_report"]);
        Permission::updateOrCreate(["name" => "perform_uniform_variance_report_review"], ["module" => "uniform", "submodule" => "variance_report"]);
        Permission::updateOrCreate(["name" => "perform_uniform_variance_report_post"], ["module" => "uniform", "submodule" => "variance_report"]);
        Permission::updateOrCreate(["name" => "view_uniform_period_lock"], ["module" => "maintenance", "submodule" => "period_lock"]);
        Permission::updateOrCreate(["name" => "perform_uniform_period_lock"], ["module" => "maintenance", "submodule" => "period_lock"]);
        Permission::updateOrCreate(["name" => "view_uniform_free_uniform_limit"], ["module" => "maintenance", "submodule" => "free_uniform_limit"]);
        Permission::updateOrCreate(["name" => "perform_uniform_free_uniform_limit"], ["module" => "maintenance", "submodule" => "free_uniform_limit"]);
    }
}
