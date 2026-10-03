<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PettyCashAuditRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate([
            "name" => "pettycash-audit",
            "guard_name" => "web"
        ]);

        $role->syncPermissions(["view_pettycash_audit_module", "perform_pettycash_audit_module"]);

        // para sa ibang department na dapat makapag-open ng audit module pero view-only
        // lang - walang perform_pettycash_audit_module kaya walang Review/Post/Add/Edit access
        $viewerRole = Role::firstOrCreate([
            "name" => "pettycash-audit-viewer",
            "guard_name" => "web"
        ]);

        $viewerRole->syncPermissions(["view_pettycash_audit_module"]);
    }
}
