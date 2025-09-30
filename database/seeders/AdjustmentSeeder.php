<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Adjustment;
use App\Models\Employee;

class AdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/biometrics/adjustments.json');
        $jsonData = file_get_contents($jsonPath);
        $adjustments = json_decode($jsonData, true);
        $unique_adjustment_details = array_map("unserialize", array_unique(array_map("serialize", $adjustments["tbl_bio_adjustments"])));

        foreach ($unique_adjustment_details as $adjustment_detail) {

            $employee = Employee::where("biometric_number", $adjustment_detail['employee_id'])->first();

            if (!$employee) {
                $this->command->info("Employee ID: {$adjustment_detail['employee_id']} not found");
                continue;
            }

            $adjustmentSeed = Adjustment::create([
                "employee_id" => $employee->id,
                "biometric_number" => $employee->biometric_number,
                "date" => $adjustment_detail["date"],
                "days" => $adjustment_detail["reg_days"],
                "cancelled_rd" => $adjustment_detail["cancelled_rd"],
                "taxable_amount" => $adjustment_detail["adjust_taxable"],
                "amount_paid" => $adjustment_detail["amount_paid"],
                "correct_amount_paid" => $adjustment_detail["should_be_paid"],
                "actual_schedule" => $adjustment_detail["actual_schedule"],
                "type" => $adjustment_detail["adjustment_type"],
                "status" => $adjustment_detail["status"],
                "remarks" => $adjustment_detail["remarks"],
                "added_by" => null,
                "added_at" => $adjustment_detail["added_date"],
                "approved_by" => null,
                "approved_at" => $adjustment_detail["approved_date"],
                "unapproved_by" => null,
                "unapproved_at" => $adjustment_detail["unapproved_date"],
                "timekeeping_summary_id" => null,
            ]);

            $this->command->info("ID: {$adjustmentSeed->id} out of 14263");
        }
    }
}
