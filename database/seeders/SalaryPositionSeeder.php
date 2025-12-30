<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\SalaryPosition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalaryPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/salary_position.json');
        $jsonData = file_get_contents($jsonPath);
        $salaryPositions = json_decode($jsonData, true);

        foreach ($salaryPositions["tbl_salaryposition"] as $position) {
            $positionID = ltrim($position['Position'], '0');

            $pos = Position::where("id", $positionID)->first();

            if ($pos) {
                SalaryPosition::create([
                    "position_id"=> $pos->id,
                    "basic_salary"=> $position['BasicSalary'],
                    "rate_category"=> $position['RateCategory'],
                    "status"=> strtolower($position['status']),
                ]);
            }
        }
          
    }
}
