<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where("name","admin")->first();
        User::create([
            'employee_number' => 'STF0001',
            'first_name' => 'Kea',
            'last_name' => 'Bartolome',
            'email' => 'keabartolome7@gmail.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'status' => 'active'
        ]);
    }
}
