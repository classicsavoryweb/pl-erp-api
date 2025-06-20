<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/contracts.json');
        $jsonData = file_get_contents($jsonPath);
        $contracts = json_decode($jsonData, true);

        // $uniqueContracts = array_map("unserialize", array_unique(array_map("serialize", $employees["tbl_econtract"])));
        foreach ($contracts["tbl_econtract"] as $contract) {
            $employee = Employee::where("employee_number", $contract["employee_id"])->first();

            if ($employee) {
                $status = $contract['contract_status'];
                if ($employee->status === "active") {
                    if ($employee->employee_status->description === "Regular") {
                        $status = "inactive";
                    }
                    if (
                        $employee->employee_status->description === "Contractual" &&
                        Carbon::now()->isAfter(Carbon::parse($contract["contract_end"]))
                    ) {
                        $status = "inactive";
                    }
                } else {
                    $status = "inactive";
                }

                Contract::create([
                    "employee_id" => $employee->id,
                    "employee_number" => $employee->employee_number,
                    "contract_number" => $contract["contract_no"],
                    "remarks" => $contract["remarks"],
                    "contract_start_date" => $contract["contract_start"],
                    "contract_end_date" => $contract["contract_end"],
                    "status" => $status,
                ]);
            }
        }
    }
}
