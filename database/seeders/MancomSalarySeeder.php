<?php

namespace Database\Seeders;

use App\Models\MancomSalary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MancomSalarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MancomSalary::updateOrCreate(
            [
                "employee_number" => "M004"
            ],[
                "salary" => "150000"
            ]
        );

        MancomSalary::updateOrCreate(
            [
                "employee_number" => "M003"
            ],[
                "salary" => "150000"
            ]
        );

        MancomSalary::updateOrCreate(
            [
                "employee_number" => "948"
            ],[
                "salary" => "150000"
            ]
        );

        MancomSalary::updateOrCreate(
            [
                "employee_number" => "0007413"
            ],[
                "salary" => "150000"
            ]
        );

        MancomSalary::updateOrCreate(
            [
                "employee_number" => "M002"
            ],[
                "salary" => "100000"
            ]
        );

        MancomSalary::updateOrCreate(
            [
                "employee_number" => "0005656"
            ],[
                "salary" => "400000"
            ]
        );
    }
}
