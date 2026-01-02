<?php

namespace Database\Seeders;

use App\Models\ActingCapacity;
use App\Models\ActingCapacityExtension;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActingCapacitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/acting_capacity.json');
        $jsonData = file_get_contents($jsonPath);
        $actingCapacity = json_decode($jsonData, true);

        foreach($actingCapacity["tbl_actingposition"] as $acting) {
            $employee = Employee::where("employee_number", $acting["employeeID"])->first();

            $currentPosition =  Position::find(ltrim($acting["currentpositionID"], '0'));
            $position = Position::find(ltrim($acting["recommendedpositionID"], '0'));
            $branch = Branch::find($employee->branch_id);
            $actor = "";

            $oicNumber = $branch->branch_head_employee_number;
            if ( strtolower($position->station->name) === "back of the house") {
                $oicNumber = $branch->kitchen_oic_employee_number;
            }

            if ($acting["date_added"]) {
                $actor = Employee::whereRaw("
                    CONCAT(
                        first_name, ' ',
                        LEFT(middle_name, 1), '. ',
                        last_name
                    ) = ?
                ", [strtoupper($acting["added_by"]) ])->first();
            }

            $createdCapacity = ActingCapacity::create([
                "employee_id" => $employee->id,
                "branch_id" => $employee->branch_id ?? 0,
                "current_position_id" => $currentPosition->id,
                "recommended_position_id" => $position->id,
                "start_date" => $acting["start_date"],
                "end_date" => $acting["end_date"],
                "remarks" => $acting["reason"],
                "period" => $acting["period"],
                "bond_amount" => $acting["bond_amt"],
                "effectivity_date" => $acting["effective_date"],
                "status" => strtolower($acting["status"]),
                "generated_by" => $acting["generate_by"] ? $actor?->id ?? null : null,
                "generated_date" => $acting["generate_date"],
                "confirmed_date" => $acting["confirmed_date"],
                "confirmed_by" => $acting["confirmed_by"] ? $actor?->id ?? null : null,
                "rejected_reason" => $acting["rejectedreason"],
                "rejected_date" => $acting["rejected_date"],
                "rejected_by" => $acting["rejected_by"] ? $actor?->id ?? null : null,
                "bond_year" => $acting["bondyear"],
                "oic_employee_number" => $oicNumber,
                "branch_assignment_id" => $branch->id,
            ]);

            for ($i = 2; $i <= 5; $i++) {
                if ($acting["reason_".$i]) {
                    ActingCapacityExtension::create([
                        "acting_capacity_id" => $createdCapacity->id,
                        "end_date" => $acting["end_date".$i],
                        "reason" => $acting["reason_".$i],
                        "remarks" => $acting["remarks_".$i],
                    ]);
                }
            }
        }
    }
}
