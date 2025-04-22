<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'employee_number' => 'STF0001',
            'first_name' => 'Kea',
            'last_name' => 'Bartolome',
            'email' => 'keabartolome7@gmail.com',
            'password' => bcrypt('password'),
            'role_id' => '1',
            'status' => 'active'
        ]);
    }
}
