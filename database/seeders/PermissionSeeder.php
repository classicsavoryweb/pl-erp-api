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
        Permission::firstOrCreate(["name" => "can_view_administrators_module"]);
        Permission::firstOrCreate(["name" => "can_view_roles_and_permissions"]);
        Permission::firstOrCreate(["name" => "can_perform_roles_and_permissions"]);
        Permission::firstOrCreate(["name" => "can_view_users"]);
        Permission::firstOrCreate(["name" => "can_perform_users"]);
        Permission::firstOrCreate(["name" => "can_view_activity_logs"]);
        // Permission::firstOrCreate(["name" => "can_view_settings"]);
        // Permission::firstOrCreate(["name" => "can_perform_settings"]);

        //maintenance module
        Permission::firstOrCreate(["name" => "can_view_maintenance_module"]);
        Permission::firstOrCreate(["name" => "can_view_positions"]);
        Permission::firstOrCreate(["name" => "can_perform_positions"]);
        Permission::firstOrCreate(["name" => "can_view_branches"]);
        Permission::firstOrCreate(["name" => "can_perform_branches"]);
        Permission::firstOrCreate(["name" => "can_view_employee_status"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_status"]);
        Permission::firstOrCreate(["name" => "can_view_stations"]);
        Permission::firstOrCreate(["name" => "can_perform_stations"]);
        Permission::firstOrCreate(["name" => "can_view_agencies"]);
        Permission::firstOrCreate(["name" => "can_perform_agencies"]);
        Permission::firstOrCreate(["name" => "can_view_offenses"]);
        Permission::firstOrCreate(["name" => "can_perform_offenses"]);
        Permission::firstOrCreate(["name" => "can_view_training_titles"]);
        Permission::firstOrCreate(["name" => "can_perform_training_titles"]);
        Permission::firstOrCreate(["name" => "can_view_diagnosis"]);
        Permission::firstOrCreate(["name" => "can_perform_diagnosis"]);
        Permission::firstOrCreate(["name" => "can_view_rate_per_group"]);
        Permission::firstOrCreate(["name" => "can_perform_rate_per_group"]);
        Permission::firstOrCreate(["name" => "can_view_salary_positions"]);
        Permission::firstOrCreate(["name" => "can_perform_salary_positions"]);
        Permission::firstOrCreate(["name" => "can_view_bank_account_types"]);
        Permission::firstOrCreate(["name" => "can_perform_bank_account_types"]);        
        Permission::firstOrCreate(["name" => "can_view_vaccine_brand"]);
        Permission::firstOrCreate(["name" => "can_perform_vaccine_brand"]);  
        Permission::firstOrCreate(["name" => "can_view_coe_reasons"]);
        Permission::firstOrCreate(["name" => "can_perform_coe_reasons"]);  

        //employees module
        Permission::firstOrCreate(["name" => "can_view_employees_module"]);
        Permission::firstOrCreate(["name" => "can_view_employee_tracking"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_tracking"]);
        Permission::firstOrCreate(["name" => "can_view_employee_branches"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_branches"]);
        Permission::firstOrCreate(["name" => "can_view_employee_offenses"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_offenses"]);
        Permission::firstOrCreate(["name" => "can_view_employee_position"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_position"]);
        Permission::firstOrCreate(["name" => "can_view_employee_requirements"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_requirements"]);
        Permission::firstOrCreate(["name" => "can_view_employee_bank_details"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_bank_details"]);
        Permission::firstOrCreate(["name" => "can_view_employee_training_titles"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_training_titles"]);
        Permission::firstOrCreate(["name" => "can_view_employee_vaccination"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_vaccination"]);
        Permission::firstOrCreate(["name" => "can_view_employee_health_and_safety"]);
        Permission::firstOrCreate(["name" => "can_perform_employee_health_and_safety"]);
        Permission::firstOrCreate(["name" => "can_view_employee_audit_logs"]);


        //reports module
        Permission::firstOrCreate(["name" => "can_view_reports_module"]);
        Permission::firstOrCreate(["name" => "can_view_reports_offenses"]);
        Permission::firstOrCreate(["name" => "can_perform_reports_offenses"]);
        Permission::firstOrCreate(["name" => "can_view_reports_vaccination"]);
        Permission::firstOrCreate(["name" => "can_perform_reports_vaccination"]);
        Permission::firstOrCreate(["name" => "can_view_reports_health_and_safety"]);
        Permission::firstOrCreate(["name" => "can_perform_reports_health_and_safety"]);
        Permission::firstOrCreate(["name" => "can_view_reports_coe_requests"]);
        Permission::firstOrCreate(["name" => "can_perform_reports_coe_requests"]);
    }
}
