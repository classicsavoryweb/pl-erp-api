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
        Permission::firstOrCreate(["name" => "view_administrators_module"]);
        Permission::firstOrCreate(["name" => "view_roles_and_permissions"]);
        Permission::firstOrCreate(["name" => "perform_roles_and_permissions"]);
        Permission::firstOrCreate(["name" => "view_users"]);
        Permission::firstOrCreate(["name" => "perform_users"]);
        Permission::firstOrCreate(["name" => "view_activity_logs"]);
        // Permission::firstOrCreate(["name" => "view_settings"]);
        // Permission::firstOrCreate(["name" => "perform_settings"]);

        //maintenance module
        Permission::firstOrCreate(["name" => "view_maintenance_module"]);
        Permission::firstOrCreate(["name" => "view_positions"]);
        Permission::firstOrCreate(["name" => "perform_positions"]);
        Permission::firstOrCreate(["name" => "view_branches"]);
        Permission::firstOrCreate(["name" => "perform_branches"]);
        Permission::firstOrCreate(["name" => "view_employee_status"]);
        Permission::firstOrCreate(["name" => "perform_employee_status"]);
        Permission::firstOrCreate(["name" => "view_stations"]);
        Permission::firstOrCreate(["name" => "perform_stations"]);
        Permission::firstOrCreate(["name" => "view_agencies"]);
        Permission::firstOrCreate(["name" => "perform_agencies"]);
        Permission::firstOrCreate(["name" => "view_offenses"]);
        Permission::firstOrCreate(["name" => "perform_offenses"]);
        Permission::firstOrCreate(["name" => "view_training_titles"]);
        Permission::firstOrCreate(["name" => "perform_training_titles"]);
        Permission::firstOrCreate(["name" => "view_diagnosis"]);
        Permission::firstOrCreate(["name" => "perform_diagnosis"]);
        Permission::firstOrCreate(["name" => "view_rate_per_group"]);
        Permission::firstOrCreate(["name" => "perform_rate_per_group"]);
        Permission::firstOrCreate(["name" => "view_salary_positions"]);
        Permission::firstOrCreate(["name" => "perform_salary_positions"]);
        Permission::firstOrCreate(["name" => "view_bank_account_types"]);
        Permission::firstOrCreate(["name" => "perform_bank_account_types"]);        
        Permission::firstOrCreate(["name" => "view_vaccine_brand"]);
        Permission::firstOrCreate(["name" => "perform_vaccine_brand"]);  
        Permission::firstOrCreate(["name" => "view_coe_reasons"]);
        Permission::firstOrCreate(["name" => "perform_coe_reasons"]);
        Permission::firstOrCreate(["name" => "view_acting_capacity_reasons"]);
        Permission::firstOrCreate(["name" => "perform_acting_capacity_reasons"]); 
        Permission::firstOrCreate(["name" => "view_sanctions"]);
        Permission::firstOrCreate(["name" => "perform_sanctions"]);
        Permission::firstOrCreate(["name" => "view_schedule_type"]);
        Permission::firstOrCreate(["name" => "perform_schedule_type"]);
        Permission::firstOrCreate(["name" => "view_schedule_masterlist"]);
        Permission::firstOrCreate(["name" => "perform_schedule_masterlist"]);
        Permission::firstOrCreate(["name" => "view_holidays"]);
        Permission::firstOrCreate(["name" => "perform_holidays"]);

        //employees module
        Permission::firstOrCreate(["name" => "view_employees_module"]);
        Permission::firstOrCreate(["name" => "view_employee_tracking"]);
        Permission::firstOrCreate(["name" => "perform_employee_tracking"]);
        Permission::firstOrCreate(["name" => "export_employee_tracking"]);
        Permission::firstOrCreate(["name" => "view_acting_capacity"]);
        Permission::firstOrCreate(["name" => "export_acting_capacity"]);
        Permission::firstOrCreate(["name" => "perform_acting_capacity"]);
        Permission::firstOrCreate(["name" => "generate_acting_capacity"]);
        Permission::firstOrCreate(["name" => "approve_acting_capacity"]);
        Permission::firstOrCreate(["name" => "reject_acting_capacity"]);

        Permission::firstOrCreate(["name" => "view_employee_branches"]);
        Permission::firstOrCreate(["name" => "perform_employee_branches"]);
        Permission::firstOrCreate(["name" => "view_employee_offenses"]);
        Permission::firstOrCreate(["name" => "perform_employee_offenses"]);
        Permission::firstOrCreate(["name" => "view_employee_position"]);
        Permission::firstOrCreate(["name" => "perform_employee_position"]);
        Permission::firstOrCreate(["name" => "view_employee_contracts"]);
        Permission::firstOrCreate(["name" => "perform_employee_contracts"]);
        Permission::firstOrCreate(["name" => "view_employee_requirements"]);
        Permission::firstOrCreate(["name" => "perform_employee_requirements"]);
        Permission::firstOrCreate(["name" => "view_employee_bank_details"]);
        Permission::firstOrCreate(["name" => "perform_employee_bank_details"]);
        Permission::firstOrCreate(["name" => "view_employee_training_titles"]);
        Permission::firstOrCreate(["name" => "perform_employee_training_titles"]);
        Permission::firstOrCreate(["name" => "view_employee_vaccination"]);
        Permission::firstOrCreate(["name" => "perform_employee_vaccination"]);
        Permission::firstOrCreate(["name" => "view_employee_audit_logs"]);


        //all records module
        // Permission::firstOrCreate(["name" => "view_all_records_health_and_safety"]);
        // Permission::firstOrCreate(["name" => "perform_all_records_health_and_safety"]);
        Permission::firstOrCreate(["name" => "view_all_records_module"]);
        Permission::firstOrCreate(["name" => "view_all_records_offenses"]);
        Permission::firstOrCreate(["name" => "perform_all_records_offenses"]);
        Permission::firstOrCreate(["name" => "export_all_records_offenses"]);
        Permission::firstOrCreate(["name" => "view_all_records_vaccination"]);
        Permission::firstOrCreate(["name" => "perform_all_records_vaccination"]);
        Permission::firstOrCreate(["name" => "view_all_records_coe_requests"]);
        Permission::firstOrCreate(["name" => "perform_all_records_coe_requests"]);
        Permission::firstOrCreate(["name" => "cancel_all_records_coe_requests"]);
        Permission::firstOrCreate(["name" => "approve_all_records_coe_requests"]);
        Permission::firstOrCreate(["name" => "view_all_records_employment_status"]);
        Permission::firstOrCreate(["name" => "perform_all_records_employment_status"]);
        Permission::firstOrCreate(["name" => "view_all_records_tardiness"]);
        Permission::firstOrCreate(["name" => "perform_all_records_tardiness"]);
        Permission::firstOrCreate(["name" => "import_all_records_tardiness"]);
        Permission::firstOrCreate(["name" => "approve_all_records_tardiness"]);
        Permission::firstOrCreate(["name" => "void_all_records_tardiness"]);

        //transactions module
        Permission::firstOrCreate(["name" => "view_transactions_module"]);
        Permission::firstOrCreate(["name" => "view_transactions_schedule_time"]);
        Permission::firstOrCreate(["name" => "perform_transactions_schedule_time"]);
        Permission::firstOrCreate(["name" => "view_transactions_actual_time"]);
        Permission::firstOrCreate(["name" => "perform_transactions_actual_time"]);
        Permission::firstOrCreate(["name" => "import_all_transactions_schedule_time"]);
        Permission::firstOrCreate(["name" => "view_transactions_time_keeping"]);
        Permission::firstOrCreate(["name" => "perform_transactions_time_keeping"]);
        Permission::firstOrCreate(["name" => "view_transactions_time_keeping_summary"]);
        Permission::firstOrCreate(["name" => "perform_transactions_time_keeping_summary"]);
        Permission::firstOrCreate(["name" => "view_transactions_overtime"]);
        Permission::firstOrCreate(["name" => "perform_transactions_overtime"]);
        Permission::firstOrCreate(["name" => "view_transactions_incorrect_logs"]);
        Permission::firstOrCreate(["name" => "perform_transactions_incorrect_logs"]);
        Permission::firstOrCreate(["name" => "import_all_transactions_overtime"]);
        Permission::firstOrCreate(["name" => "pay_all_transactions_overtime"]);
        Permission::firstOrCreate(["name" => "unpay_all_transactions_overtime"]);
    }
}
