<?php

namespace Database\Seeders;

use App\Models\TardinessSanction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TardinessSanctionSeeder extends Seeder
{

    public function run(): void
    {
        TardinessSanction::create([
            "count" => "1st Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by Written Reprimand on the First Offense.",
            "rank" => "1"
        ]);
        TardinessSanction::create([
            "count" => "2nd Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 25% Performance Incentives Deduction on the Second Offense.",
            "rank" => "2"
        ]);
        TardinessSanction::create([
            "count" => "3rd Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 25% Performance Incentives Deduction on the Third Offense.",
            "rank" => "3"
        ]);

        TardinessSanction::create([
            "count" => "4th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 50 % Performance Incentives Deduction on the Fourth Offense.",
            "rank" => "4"
        ]);
        TardinessSanction::create([
            "count" => "5th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 50 % Performance Incentives Deduction on the Fifth Offense.",
            "rank" => "5"
        ]);
        TardinessSanction::create([
            "count" => "6th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 75 % Performance Incentives Deduction on the Sixth Offense.",
            "rank" => "6"
        ]);
        TardinessSanction::create([
            "count" => "7th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 75 % Performance Incentives Deduction on the Seventh Offense.",
            "rank" => "7"
        ]);
        TardinessSanction::create([
            "count" => "8th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 100 % Performance Incentives Deduction on the Eight Offense.",
            "rank" => "8"
        ]);
        TardinessSanction::create([
            "count" => "9th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 100 % Performance Incentives Deduction on the Ninth Offense.",
            "rank" => "9"
        ]);
        TardinessSanction::create([
            "count" => "10th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 100 % Performance Incentives Deduction on the Tenth Offense.",
            "rank" => "10"
        ]);
        TardinessSanction::create([
            "count" => "11th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 100 % Performance Incentives Deduction on the Eleventh Offense.",
            "rank" => "11"
        ]);
        TardinessSanction::create([
            "count" => "12th Offense",
            "punishment" => "Pursuant to the Code of Discipline, this may constitutes possible violation under First Degree Offense punishable by 100 % Performance Incentives Deduction on the Twelfth Offense.",
            "rank" => "12"
        ]);
    }
}
