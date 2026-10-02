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
    }
}
