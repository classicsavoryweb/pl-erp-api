<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\BiotimeLog;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(AssignPermissionToAdminSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(HRRoleSeeder::class);
        $this->call(MaintenanceSeeder::class);
        $this->call(EmployeeRecordSeeder::class);
        // $employees = Employee::where("branch_id", 107)->where("status", "active")->get();

        // foreach ($employees as $employee) {
        //     $logs = BiotimeLog::where("employee_id", $employee->id)
        //     ->get();

        //     foreach ($logs as $log) {
        //         $log->punches()->delete();
        //         $log->delete();
        //     }
        // }

    }
}
