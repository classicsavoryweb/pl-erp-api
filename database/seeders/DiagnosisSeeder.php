<?php

namespace Database\Seeders;

use App\Models\Diagnosis;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiagnosisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $diagnosis = [
            [
              "auto_id"=> "1",
              "description"=> "Tuberculosis",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> "admin",
              "updated_date"=> "2023-04-22 12=>03=>03"
            ],
            [
              "auto_id"=> "2",
              "description"=> "Hypertension",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "3",
              "description"=> "High Cholesterol",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "4",
              "description"=> "Hypertensive Cardiovascular Disease",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> "admin",
              "updated_date"=> "2023-04-22 12=>12=>26"
            ],
            [
              "auto_id"=> "5",
              "description"=> "Coronary Artery Disease",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "6",
              "description"=> "Gall Stone",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "7",
              "description"=> "Kidney Stone",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "8",
              "description"=> "Chronic Kidney Disease",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "9",
              "description"=> "Psoriasis",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "10",
              "description"=> "Vertigo",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "11",
              "description"=> "Hyperthyroidism/Hypothyroidism",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "12",
              "description"=> "Gout",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "13",
              "description"=> "Arthritis",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "14",
              "description"=> "Gouty Arthritis",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "15",
              "description"=> "UTI (Urinary Tract Infection)",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "16",
              "description"=> "Pneumonia",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "17",
              "description"=> "Astigmatism",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "18",
              "description"=> "Myopia",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "19",
              "description"=> "Diabetes",
              "status"=> "active",
              "added_by"=> "Programmer - Mark",
              "added_date"=> "2023-04-22 12=>01=>13",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "21",
              "description"=> "Toothache",
              "status"=> "active",
              "added_by"=> "jdelrosario",
              "added_date"=> "2023-05-08 15=>38=>42",
              "updated_by"=> null,
              "updated_date"=> null
            ],
            [
              "auto_id"=> "22",
              "description"=> "Needs proper diet",
              "status"=> "active",
              "added_by"=> "Jo Ann J. Del Rosario",
              "added_date"=> "2024-02-26 18=>12=>40",
              "updated_by"=> null,
              "updated_date"=> null
            ]
        ];

        foreach($diagnosis as $sick) {
            Diagnosis::create([
                "description"=> $sick["description"],
                "status"=> strtolower($sick["status"]),
            ]);
        }
    }
}
