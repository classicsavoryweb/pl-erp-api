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
        Permission::updateOrCreate(["name" => "view_administrators_module"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "view_roles_and_permissions"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "perform_roles_and_permissions"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "view_users"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "perform_users"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "view_activity_logs"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "perform_database_backups"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "view_database_backups"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "view_tardiness_disabler"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "has_general_dashboard"], ["module" => "administrators"]);
        Permission::updateOrCreate(["name" => "has_analytic_dashboard_hr"], ["module" => "administrators"]);
        // Permission::updateOrCreate(["name" => "view_settings"]);
        // Permission::updateOrCreate(["name" => "perform_settings"]);

        //maintenance module
        Permission::updateOrCreate(["name" => "view_maintenance_module"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_positions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_positions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_branches"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_region"], ["module" => "maintenance"]);
        $legacyPerformBranches = Permission::where("name", "perform_branches")->first();
        $createBranches = Permission::updateOrCreate(["name" => "create_branches"], ["module" => "maintenance"]);
        if ($legacyPerformBranches) {
            foreach ($legacyPerformBranches->roles as $role) {
                $role->givePermissionTo($createBranches);
            }
            $legacyPerformBranches->delete();
        }
        Permission::where("name", "edit_branches")->delete();
        Permission::updateOrCreate(["name" => "perform_region"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "edit_branches_all"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "edit_branches_oic"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_employee_status"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_employee_status"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_stations"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_stations"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_agencies"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_agencies"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_offenses"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_offenses"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_training_titles"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_training_titles"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_diagnosis"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_diagnosis"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_rate_per_group"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_rate_per_group"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_salary_positions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_salary_positions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_bank_account_types"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_bank_account_types"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_vaccine_brand"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_vaccine_brand"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_coe_reasons"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_coe_reasons"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_acting_capacity_reasons"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_acting_capacity_reasons"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_sanctions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_sanctions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_schedule_type"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_schedule_type"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_schedule_masterlist"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_schedule_masterlist"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_holidays"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_holidays"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_sss"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_sss"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_tax"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_tax"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_philhealth"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_philhealth"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_pagibig"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_pagibig"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_deductions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_deductions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_additional_income"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_additional_income"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_period_definition"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_period_definition"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_cshub_transaction_type"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_cshub_transaction_type"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_atd_access_type"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_adjustment_types"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_adjustment_types"], ["module" => "maintenance"]);

        Permission::updateOrCreate(["name" => "view_contributions_and_tax"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_contributions_and_tax"], ["module" => "maintenance"]);

        Permission::updateOrCreate(["name" => "view_terms"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_terms"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_last_pay_setup"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_last_pay_setup"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_tardiness_sanctions"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_tardiness_sanctions"], ["module" => "maintenance"]);

        

        //employees module
        
        Permission::updateOrCreate(["name" => "view_employees_module"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_tracking"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_tracking"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "export_employee_tracking"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_acting_capacity"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "export_acting_capacity"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_acting_capacity"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "generate_acting_capacity"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "approve_acting_capacity"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "reject_acting_capacity"], ["module" => "employees"]);

        Permission::updateOrCreate(["name" => "view_employee_branches"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_branches"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_offenses"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_offenses"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_position"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_promotion_history"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_position"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_contracts"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_contracts"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_requirements"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_requirements"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_bank_details"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_bank_details"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_training_titles"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_training_titles"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_vaccination"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "perform_employee_vaccination"], ["module" => "employees"]);
        Permission::updateOrCreate(["name" => "view_employee_audit_logs"], ["module" => "employees"]);

        //all records module
        // Permission::updateOrCreate(["name" => "view_all_records_health_and_safety"]);
        // Permission::updateOrCreate(["name" => "perform_all_records_health_and_safety"]);
        Permission::updateOrCreate(["name" => "view_all_records_module"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "view_all_records_offenses"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "perform_all_records_offenses"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "export_all_records_offenses"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "view_all_records_vaccination"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "perform_all_records_vaccination"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "view_all_records_coe_requests"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "perform_all_records_coe_requests"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "cancel_all_records_coe_requests"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "approve_all_records_coe_requests"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "view_all_records_employment_status"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "perform_all_records_employment_status"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "view_all_records_tardiness"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "perform_all_records_tardiness"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "import_all_records_tardiness"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "approve_all_records_tardiness"], ["module" => "all_records"]);
        Permission::updateOrCreate(["name" => "void_all_records_tardiness"], ["module" => "all_records"]);

        //transactions module
        Permission::updateOrCreate(["name" => "view_transactions_module"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "view_transactions_schedule_time"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "perform_transactions_schedule_time"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "transfer_time_keeping_branch"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_schedule_time"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "view_transactions_actual_time"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "perform_transactions_actual_time"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "import_transactions_actual_time"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "import_all_transactions_schedule_time"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_actual_logs"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "view_transactions_time_keeping"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "perform_transactions_time_keeping"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_time_keeping"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "view_transactions_time_keeping_summary"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "perform_transactions_time_keeping_summary"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_time_keeping_summary"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "view_transactions_adjustments"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "perform_transactions_adjustments"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "approve_transactions_adjustments"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "unapprove_transactions_adjustments"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "view_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "perform_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "import_all_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "export_all_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "approve_all_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "unapprove_all_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "void_all_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "force_adjust_all_transactions_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "view_transactions_travel_overtime"], ["module" => "transactions"]);
        Permission::updateOrCreate(["name" => "perform_transactions_travel_overtime"], ["module" => "transactions"]);

        //compensation modules
        Permission::updateOrCreate(["name" => "view_compensation_module"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_payroll_list"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_payroll_list"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_cutoff_setup"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_cutoff_setup"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "initialize_compensation_cutoff_setup"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "generate_payroll_compensation_cutoff_setup"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "finalize_compensation_cutoff_setup"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "recompute_incentives_compensation_cutoff_setup"], ["module" => "compensation"]);

        Permission::updateOrCreate(["name" => "view_compensation_deductions"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_deductions"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "end_loan_compensation_deductions"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "edit_compensation_deductions"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "export_all_compensation_deductions"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_payroll"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_payroll"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_incentives"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_incentives"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_entries"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_entries"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "export_all_compensation_entries"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_converter"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_converter"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_payroll_prooflist"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_payroll_prooflist"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_last_pay"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_last_pay"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_additional_income"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_additional_income"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_compensation_atd"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_compensation_atd"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "export_compensation_atd"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_incentives_management"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_incentives_management"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_incentives_bracket"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_incentives_bracket"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_incentives_per_employee"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_incentives_per_employee"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "view_incentives_position"], ["module" => "compensation"]);
        Permission::updateOrCreate(["name" => "perform_incentives_position"], ["module" => "compensation"]);

        //reports modules
        Permission::updateOrCreate(["name" => "view_reports_module"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_statutory_reports"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_pagibig"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_philhealth"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_sss"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_bdo_cash_card"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_13th_month_pay"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_13th_month_pay_entries"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_pay_slip"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_pay_slip"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "generate_13th_month_pay"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_bir"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_bir"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_bir_1601c"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_bir_1601c"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_deductions"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_deductions"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_incentive_attendance"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_incentive_attendance"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_incentive_report"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_incentive_report"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_timekeeping_summary_report"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_timekeeping_summary_report"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "view_reports_bank_account_history"], ["module" => "reports"]);
        Permission::updateOrCreate(["name" => "perform_reports_bank_account_history"], ["module" => "reports"]);


        //uniform (maintenance)
        Permission::updateOrCreate(["name" => "view_maintenance_suppliers"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_maintenance_suppliers"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_maintenance_sizes"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_maintenance_sizes"], ["module" => "maintenance"]);
        //uniform module (section)
        Permission::updateOrCreate(["name" => "view_uniform_module"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_item_masterlist"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "perform_uniform_item_masterlist"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_request_and_issuance"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "perform_uniform_request_and_issuance"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_replenishment_request"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "perform_uniform_replenishment_request"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_receiving_stocks"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "perform_uniform_receiving_stocks"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_weighted_average"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "perform_uniform_weighted_average"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_physical_count"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "perform_uniform_physical_count"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_variance_report"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "perform_uniform_variance_report"], ["module" => "uniform"]);
        Permission::updateOrCreate(["name" => "view_uniform_period_lock"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_period_lock"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "view_uniform_free_uniform_limit"], ["module" => "maintenance"]);
        Permission::updateOrCreate(["name" => "perform_uniform_free_uniform_limit"], ["module" => "maintenance"]);
    }
}
