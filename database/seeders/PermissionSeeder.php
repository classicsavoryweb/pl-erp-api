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
        Permission::updateOrCreate(["name" => "can_view_administrators_module"]);
        Permission::updateOrCreate(["name" => "can_view_roles_and_permissions"]);
        Permission::updateOrCreate(["name" => "can_view_users"]);
        Permission::updateOrCreate(["name" => "can_perform_roles_and_permissions"]);
        Permission::updateOrCreate(["name" => "can_perform_users"]);

        //maintenance module
        Permission::updateOrCreate(["name" => "can_view_maintenance_module"]);
        Permission::updateOrCreate(["name" => "can_view_positions"]);
        Permission::updateOrCreate(["name" => "can_view_branches"]);
        Permission::updateOrCreate(["name" => "can_view_employee_status"]);
        Permission::updateOrCreate(["name" => "can_view_stations"]);
        Permission::updateOrCreate(["name" => "can_view_agencies"]);
        Permission::updateOrCreate(["name" => "can_view_offenses"]);
        Permission::updateOrCreate(["name" => "can_perform_positions"]);
        Permission::updateOrCreate(["name" => "can_perform_branches"]);
        Permission::updateOrCreate(["name" => "can_perform_employee_status"]);
        Permission::updateOrCreate(["name" => "can_perform_stations"]);
        Permission::updateOrCreate(["name" => "can_perform_agencies"]);
        Permission::updateOrCreate(["name" => "can_perform_offenses"]);
    }
}
