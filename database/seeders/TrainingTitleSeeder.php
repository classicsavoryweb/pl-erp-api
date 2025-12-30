<?php

namespace Database\Seeders;

use App\Models\TrainingTitle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TrainingTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $jsonPath = public_path('jsons/titles.json');
        $jsonData = file_get_contents($jsonPath);
        $titles = json_decode($jsonData, true);

        foreach( $titles["tbl_titles"] as $title ) {
            TrainingTitle::create([
                "option" => $title["option"],
                "title" => $title["title"],
                "venue" => $title["venue"],
                "speaker" => $title["speaker"],
                "status" => strtolower($title["status"]),
            ]);
        }
    }
}
