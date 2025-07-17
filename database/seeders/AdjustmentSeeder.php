<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Adjustment;

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
            Adjustment::create([
                "cutoff_period" => $adjustment_detail["cutoff_period"],
                "employee_id" => $adjustment_detail["employee_id"],
                "branch" => $adjustment_detail["branch"],
                "reg_days" => $adjustment_detail["reg_days"],
                "cancelled_rd" => $adjustment_detail["cancelled_rd"],
                "adjust_taxable" => $adjustment_detail["adjust_taxable"],
                "remarks" => $adjustment_detail["remarks"],
                "date" => $adjustment_detail["date"],
                "status" => $adjustment_detail["status"],
                "new_timein" => $adjustment_detail["new_timein"],
                "new_breakout" => $adjustment_detail["new_breakout"],
                "new_breakin" => $adjustment_detail["new_breakin"],
                "new_timeout" => $adjustment_detail["new_timeout"],
                "amount_paid" => $adjustment_detail["amount_paid"],
                "should_be_paid" => $adjustment_detail["should_be_paid"],
                "adjustment_type" => $adjustment_detail["adjustment_type"],
                "added_date" => $adjustment_detail["added_date"],
                "added_by" => $adjustment_detail["added_by"],
                "approved_date" => $adjustment_detail["approved_date"],
                "approved_by" => $adjustment_detail["approved_by"],
                "load_status" => $adjustment_detail["load_status"],
                "load_date" => $adjustment_detail["load_date"],
                "unapproved_date" => $adjustment_detail["unapproved_date"],
                "unapproved_by" => $adjustment_detail["unapproved_by"],
                "actual_schedule" => $adjustment_detail["actual_schedule"],
                "schedule_timein" => $adjustment_detail["sched_timein"],
                "schedule_timeout" => $adjustment_detail["sched_timeout"],
                "cutoff_counter" => $adjustment_detail["cutoff_counter"],
            ]);
        }
    }
}
