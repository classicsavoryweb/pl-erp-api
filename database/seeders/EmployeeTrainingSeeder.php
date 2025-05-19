<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeTrainings;
use App\Models\TrainingTitle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeTrainingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/trainings.json');
        $jsonData = file_get_contents($jsonPath);
        $trainings = json_decode($jsonData, true);

        foreach ($trainings["tbl_trainings"] as $training) {
            $employee = Employee::where("employee_number", $training["employee_id"])->first();
            $currentTraining = TrainingTitle::where("title", $training["title"])->first();
            
            if ($employee && $currentTraining) {
                EmployeeTrainings::create([
                    "employee_id" => $employee->id,
                    "training_title_id" => $currentTraining->id,
                    "date" => $training["date"],
                    "score" => $training["score"],
                ]);
            }
        }
    }
}
