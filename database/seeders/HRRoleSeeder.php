<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class HRRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/hr-roles.json');
        $jsonData = file_get_contents($jsonPath);
        $roles = json_decode($jsonData, true);

        foreach ($roles['roles'] as $role) {
            $roleName = Role::create([
                "name" => $role["name"],
                "guard_name" => "web" 
            ]);

            $permissions = collect($role["permissions"])->pluck("id")->toArray();

            $roleName->syncPermissions($permissions);
        }
    }
}
