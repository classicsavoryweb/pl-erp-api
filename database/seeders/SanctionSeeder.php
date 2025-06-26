<?php

namespace Database\Seeders;

use App\Models\Sanction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SanctionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        $sanctions = [
            [
            "auto_id"=> "1",
            "description"=> "Written Reprimand",
            "rank"=>"1",
            "suspension_days"=> "0"
            ],
            [
            "auto_id"=> "2",
            "description"=> "Reminder Memo",
            "rank"=>"2",
            "suspension_days"=> "0"
            ],
            [
            "auto_id"=> "2",
            "description"=> "Final Reminder Memo",
            "rank"=>"3",
            "suspension_days"=> "0"
            ],
            [
            "auto_id"=> "3",
            "description"=> "1 Day Suspension",
            "rank"=>"4",
            "suspension_days"=> "1"
            ],
            [
            "auto_id"=> "4",
            "description"=> "2 Days Suspension",
            "rank"=>"5",
            "suspension_days"=> "2"
            ],
            [
            "auto_id"=> "5",
            "description"=> "3 Days Suspension",
            "rank"=>"6",
            "suspension_days"=> "3"
            ],
            [
            "auto_id"=> "6",
            "description"=> "4 Days Suspension",
            "rank"=>"7",
            "suspension_days"=> "4"
            ],
            [
            "auto_id"=> "7",
            "description"=> "5 Days Suspension",
            "rank"=>"8",
            "suspension_days"=> "5"
            ],
            [
            "auto_id"=> "8",
            "description"=> "6 Days Suspension",
            "rank"=>"9",
            "suspension_days"=> "6"
            ],
            [
            "auto_id"=> "9",
            "description"=> "7 Days Suspension",
            "rank"=>"10",
            "suspension_days"=> "7"
            ],
            [
            "auto_id"=> "10",
            "description"=> "8 Days Suspension",
            "rank"=>"11",
            "suspension_days"=> "8"
            ],
            [
            "auto_id"=> "11",
            "description"=> "9 Days Suspension",
            "rank"=>"12",
            "suspension_days"=> "9"
            ],
            [
            "auto_id"=> "12",
            "description"=> "10 Days Suspension",
            "rank"=>"13",
            "suspension_days"=> "10"
            ],
            [
            "auto_id"=> "13",
            "description"=> "11 Days Suspension",
            "rank"=>"14",
            "suspension_days"=> "11"
            ],
            [
            "auto_id"=> "14",
            "description"=> "12 Days Suspension",
            "rank"=>"15",
            "suspension_days"=> "12"
            ],
            [
            "auto_id"=> "15",
            "description"=> "13 Days Suspension",
            "rank"=>"16",
            "suspension_days"=> "13"
            ],
            [
            "auto_id"=> "16",
            "description"=> "14 Days Suspension",
            "rank"=>"17",
            "suspension_days"=> "14"
            ],
            [
            "auto_id"=> "17",
            "description"=> "15 Days Suspension",
            "rank"=>"18",
            "suspension_days"=> "15"
            ],
            [
            "auto_id"=> "18",
            "description"=> "16 Days Suspension",
            "rank"=>"19",
            "suspension_days"=> "16"
            ],
            [
            "auto_id"=> "19",
            "description"=> "17 Days Suspension",
            "rank"=>"20",
            "suspension_days"=> "17"
            ],
            [
            "auto_id"=> "20",
            "description"=> "18 Days Suspension",
            "rank"=>"21",
            "suspension_days"=> "18"
            ],
            [
            "auto_id"=> "21",
            "description"=> "19 Days Suspension",
            "rank"=>"22",
            "suspension_days"=> "19"
            ],
            [
            "auto_id"=> "22",
            "description"=> "20 Days Suspension",
            "rank"=>"23",
            "suspension_days"=> "20"
            ],
            [
            "auto_id"=> "23",
            "description"=> "21 Days Suspension",
            "rank"=>"24",
            "suspension_days"=> "21"
            ],
            [
            "auto_id"=> "24",
            "description"=> "22 Days Suspension",
            "rank"=>"25",
            "suspension_days"=> "22"
            ],
            [
            "auto_id"=> "25",
            "description"=> "23 Days Suspension",
            "rank"=>"26",
            "suspension_days"=> "23"
            ],
            [
            "auto_id"=> "26",
            "description"=> "24 Days Suspension",
            "rank"=>"27",
            "suspension_days"=> "24"
            ],
            [
            "auto_id"=> "27",
            "description"=> "25 Days Suspension",
            "rank"=>"28",
            "suspension_days"=> "25"
            ],
            [
            "auto_id"=> "28",
            "description"=> "26 Days Suspension",
            "rank"=>"29",
            "suspension_days"=> "26"
            ],
            [
            "auto_id"=> "29",
            "description"=> "27 Days Suspension",
            "rank"=>"30",
            "suspension_days"=> "27"
            ],
            [
            "auto_id"=> "30",
            "description"=> "28 Days Suspension",
            "rank"=>"31",
            "suspension_days"=> "28"
            ],
            [
            "auto_id"=> "31",
            "description"=> "29 Days Suspension",
            "rank"=>"32",
            "suspension_days"=> "29"
            ],
            [
            "auto_id"=> "32",
            "description"=> "30 Days Suspension",
            "rank"=>"33",
            "suspension_days"=> "30"
            ],
            [
            "auto_id"=> "33",
            "description"=> "31 Days Suspension",
            "rank"=>"34",
            "suspension_days"=> "31"
            ],
            [
            "auto_id"=> "34",
            "description"=> "AWOL instead of Termination",
            "rank"=>"35",
            "suspension_days"=> "0"
            ],
            [
            "auto_id"=> "35",
            "description"=> "Resigned instead of Termination",
            "rank"=>"36",
            "suspension_days"=> "0"
            ],
            [
            "auto_id"=> "36",
            "description"=> "EOP instead of Termination",
            "rank"=>"37",
            "suspension_days"=> "0"
            ],
            [
            "auto_id"=> "37",
            "description"=> "Terminated",
            "rank"=>"38",
            "suspension_days"=> "0"
            ]
        ];

        foreach ($sanctions as $sanction) {
            Sanction::create([
                "description" => $sanction["description"],
                "rank" => $sanction["rank"],
                "suspension_days" => $sanction["suspension_days"],
            ]);
        }

    }
}
