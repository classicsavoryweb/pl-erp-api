<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $user = env('BIOMETRICS_USERNAME');
        $password = env('BIOMETRICS_PASSWORD');
        $url = 'http://120.28.114.47:9090/iclock/api/terminals/';

        foreach($unique_branches as $branch) {

            $type = $branch["area"];
            if ($type === 'Head Office') {
            $type = 'Head Office';
            } else {
                $type = 'Branch';
            }

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
                'type' => $type
            ]);
        }

        $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])
            ->timeout(60)
            ->get($url, [
                "page_size" => 10000000000
            ]);

        if ($response->successful()) {
            $resp = $response->json();
            $terminals = $resp["data"];
            foreach($terminals as $terminal) {
                $branch = Branch::where("short_name", $terminal["area_name"])->first();
                if ($branch && strtolower($branch->status) === "active") {
                    $branch->update([
                        "device_name" => $terminal["sn"]
                    ]);
                }
            }
        }

        $jsonPath = public_path('jsons/travel_time.json');
        $jsonData = file_get_contents($jsonPath);
        $travelled_time = json_decode($jsonData, true);
        $unique_travelled_time = array_map("unserialize", array_unique(array_map("serialize", $travelled_time["travel_time"])));

        foreach ($unique_travelled_time as $travel_time) {

            $branch = Branch::where("short_name", $travel_time["branches"])->first();

            if ($branch) {
                $branch->update([
                    "travel_time" => $travel_time["time_travelled"]
                ]);
            }
        }

        $jsonPath = public_path('jsons/payroll/branches.json');
        $jsonData = file_get_contents($jsonPath);
        $branches = json_decode($jsonData, true);
        $unique_branches = array_map("unserialize", array_unique(array_map("serialize", $branches["tbl_branches"])));

        foreach ($unique_branches as $branches) {

            $branch = Branch::where("branch_number", $branches["branch_id2"])->first();

            if ($branch) {
                $branch->update([
                    "expense_debit" => $branches["expense_debit"],
                    "expense_credit" => $branches["expense_credit"],
                    "advance_debit" => $branches["advances_debit"],
                    "advance_credit" => $branches["advances_credit"]
                ]);
            }
        }

        $jsonPath = public_path('jsons/payroll/allocation_setup.json');
        $jsonData = file_get_contents($jsonPath);
        $branches = json_decode($jsonData, true);
        $unique_branches = array_map("unserialize", array_unique(array_map("serialize", $branches["tbl_allocation"])));

        foreach ($unique_branches as $branches) {

            $branch = Branch::where("branch_number", $branches["branch_id"])->first();

            if ($branch) {
                $branch->update([
                    "ho_13th_debit" => $branches["debit13"],
                    "ho_13th_debit_1" => $branches["debit123"],
                    "ho_13th_credit" => $branches["credit13"],
                    "branch_ho_13th_debit" => $branches["debit132"],
                    "branch_ho_13th_debit_1" => $branches["debit1322"],
                    "branch_ho_13th_credit" => $branches["credit132"],
                    "branch_ho_stat_debit" => $branches["debitstat2"],
                    "branch_ho_stat_credit" => $branches["creditstat2"],
                ]);
            }
        }
    }
}
