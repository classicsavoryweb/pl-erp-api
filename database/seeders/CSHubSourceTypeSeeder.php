<?php

namespace Database\Seeders;

use App\Models\CSHubSourceType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CSHubSourceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/cs_hub_source_types.json');
        $jsonData = file_get_contents($jsonPath);
        $sourceTypes = json_decode($jsonData, true);

        $transactionTypesSet = [
            'Tran_type_1', 'Tran_type_2', 'Tran_type_3', 'Tran_type_4', 'Tran_type_5', 'Tran_type_6', 'Tran_type_7'
        ];
        $examTypesSet = [
            'Exam_type_1', 'Exam_type_2', 'Exam_type_3', 'Exam_type_4', 'Exam_type_5', 'Exam_type_6', 'Exam_type_7',
            'Exam_type_8', 'Exam_type_9', 'Exam_type_10', 'Exam_type_11'
        ];

        foreach ($sourceTypes["cshub_transtype"] as $source) {
            if (in_array($source["source_type"], $transactionTypesSet)) {
                $source["source_type"] = 'Transaction';
            } elseif (in_array($source["source_type"], $examTypesSet)) {
                $source["source_type"] = 'Exam';
            } elseif ($source["source_type"] == 9999) {
                $source["source_type"] = 'N/A';
            }

            $status = ucfirst($source["status"]);
            if ($status === 'Active') {
                $status = 'active';
            } else {
                $status = 'inactive';
            }

            CSHubSourceType::create([
                "description" => $source["description"],
                "source_type" => $source["source_type"],
                "status" => $status,
            ]);
        }
    }
}
