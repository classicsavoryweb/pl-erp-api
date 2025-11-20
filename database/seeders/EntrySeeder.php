<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Entry;
use App\Models\TimeKeepingSummary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 43; $i++) {
            $jsonPath = public_path("jsons/compensation/entry_{$i}.json");

            $jsonData = file_get_contents($jsonPath);
            $data = json_decode($jsonData, true);

            foreach ($data["tbl_tmp_entry"] as $entry) {

                if ($entry["ent_block_payment"] === "Yes") {
                    $isBlockPayment = 1;
                } else {
                    $isBlockPayment = 0;
                }

                $entries = Entry::create([
                    "entry_id" => $entry["ent_tran_no"],
                    "branch_number" => $entry["branch_id"],
                    "amount" => floatval(str_replace(',', '', $entry["ent_trans_amt"])),
                    "vendor_no" => $entry["ven_no"],
                    "account_no" => $entry["acc_no"],
                    "description" => $entry["ent_desc_per_acc"] ?? null,
                    "balance_type" => $entry["ent_norm_bal"] ?? null,
                    "is_block_payment" => $isBlockPayment,
                    "status" => $entry["ent_status"] ?? null,
                    "generated_at" => $entry["date_added"],
                ]);

                $this->command->info("ID: {$entries->id} out of 1578070, File: entry_{$i}.json out of 43");
            }
        }
    }
}
