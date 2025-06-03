<?php

namespace Database\Seeders;

use App\Models\Branch;
use DateTime;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run() : void
    {

        $jsonPath = public_path('jsons/branches.json');
        $jsonData = file_get_contents($jsonPath);
        $branches = json_decode($jsonData, true);
        $unique_branches = array_map("unserialize", array_unique(array_map("serialize", $branches["tbl_branch"])));

        foreach($unique_branches as $branch) {
            Branch::create([
                "name" => $branch["branch_name"],
                "branch_number" => $branch["branch_id2"],
                "status" => $branch["status"],
                "short_name" => $branch["branch_shrt_name"],
                "address" => $branch["branch_address"],
                "location" => $branch["region"],
                "date_open" =>  $branch["date_opened"],
                "company" => $branch["company"],
                "branch_email" => $branch["branch_email"],
                "rate_code" => $branch["ratecode"],
                'branch_head_employee_number' => $branch["bh_employeeid"],
                "group_head_employee_number" => $branch["gh_employeeid"],
                'branch_head' => $branch["branch_head"],
                "group_head" => $branch["group_head"],
                'kitchen_oic_employee_number' => $branch["kitchenID"],
                'kitchen_oic' => $branch["kitchenName"],
                'mancom_employee_number' => $branch["mancomID"],
                'mancom' => $branch["mancomName"],
            ]);
        }
    }
}
