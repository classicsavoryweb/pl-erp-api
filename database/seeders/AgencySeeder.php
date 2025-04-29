<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Agency::create([
            "name" => "MSC",
            "status" => "active",
            "address" => "sample address 1",
            "employee_code" => "50"
        ]);

        Agency::create([
            "name" => "MIROF",
            "status" => "inactive",
            "address" => "sample address 2",
        ]);

        Agency::create(attributes: [
            "name" => "ARM",
            "status" => "inactive",
            "address" => "sample address 3",
        ]);

        Agency::create([
            "name" => "CNT",
            "status" => "active",
            "address" => "sample address 4",
            "employee_code" => "11"
        ]);

        Agency::create([
            "name" => "GNP",
            "status" => "active",
            "address" => "sample address 5",
            "employee_code" => "12"
        ]);
    }
}
