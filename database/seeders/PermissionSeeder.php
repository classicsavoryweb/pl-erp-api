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
        Permission::updateOrCreate(["name" => "can_create_users"]);
        Permission::updateOrCreate(["name" => "can_view_users_module"]);
        Permission::updateOrCreate(["name" => "can_create_roles_and_permissions"]);
        Permission::updateOrCreate(["name" => "can_view_roles_and_permissions"]);
    }
}
