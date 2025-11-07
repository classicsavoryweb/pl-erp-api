<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ConverterDetail;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConverterDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/converter_details.json');
        $jsonData = file_get_contents($jsonPath);
        $details = json_decode($jsonData, true);

        foreach($details["tbl_converter_details"] as $detail) {
                // {
                // "id": "1",
                // "main_id": "5",
                // "emp_id": "0012147",
                // "branch_id": "SFI00122",
                // "trans_type": "5",
                // "exam_type": null,
                // "terms": "999",
                // "amount": "500.00",
                // "source": "Manual",
                // "status": "Generated",
                // "remarks": "BROMT BATCH 69  GUEST SPEAKER ALLOWANCE",
                // "added_by": "Jenifer Taynan",
                // "added_date": "2025-04-01 11:15:19",
                // "modified_by": null,
                // "modified_date": null,
                // "transno": null,
                // "voided_by": null,
                // "voided_date": null
                // },
            $employee = Employee::where("employee_number", $detail["emp_id"])->first();
            $branch = Branch::where("branch_number", $detail["branch_id"])->first();

            if ($employee && $branch) {
                ConverterDetail::create([
                    "converter_main_id" => $detail["main_id"],
                    "employee_id" => $employee->id,
                    "branch_id" => $branch->id,
                    "transaction_type_id" => $detail["trans_type"],
                    "terms" => $detail["terms"],
                    "amount" => $detail["amount"],
                    "source" => $detail["source"],
                    "status" => $detail["status"],
                    "remarks" => $detail["remarks"],
                    "added_by" => null,
                    "transaction_number" => $detail["transno"] ?? null,
                    "created_at" => $detail["added_date"]
                ]);
            }
        }
    }
}
