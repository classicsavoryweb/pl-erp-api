<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Station;
use App\Models\EmployeeStatus;
use App\Models\Dependents;
use App\Models\Branch;
use App\Models\EducationHistory;


class AgencyEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/agency_employees.json');
        $jsonData = file_get_contents($jsonPath);
        $employees = json_decode($jsonData, true);

        $uniqueEmployees = array_map("unserialize", array_unique(array_map("serialize", $employees["tbl_agencyemployee"])));
        foreach($uniqueEmployees as $employee) {
            $position = Position::where("description", $employee["position_descr"])->first();
            $branch = Branch::where("branch_number", $employee["branch_id"])->first();
            $homeBranch = Branch::where("branch_number", $employee["HomeBranchID"])->first();
            $employmentStatus = EmployeeStatus::where("description", $employee["description"])->first();
            $station = Station::where("name", $employee["station"])->first();

            $emp = Employee::create([
                "employee_number" => $employee["employee_id2"],
                "biometric_number" => $employee["biometricID"],
                "first_name"=> $employee["first_name"],
                "middle_name"     => $employee["middle_name"],
                "last_name" => $employee["last_name"],
                "email"     => $employee["email"],
                "civil_status"    => $employee["civil_status"],
                "gender"    => $employee["gender"],
                "height"    => $employee["height"],
                "birthday"  => $employee["birthday"],
                "birthplace"=> $employee["birthplace"],
                "address"   => $employee["address"],
                "provincial_address"    => $employee["provincial_address"],
                "region"    => $employee["region"],
                "contact_number"  => $employee["contact_no"],
                "tax_type" => $employee["tax_type"],
                "tin" => $employee["tin"],
                "sss" => $employee["sss"],
                "philhealth"=> $employee["philhealth"],
                "pagibig"   => $employee["pagibig"],
                "referred_by"     => $employee["referred_by"],
                "remarks"   => $employee["remarks"],
                "date_hired"=> $employee["date_hired"],
                "employee_status_id"    => $employmentStatus?->id,
                "position_id"     => $position?->id ?? null,
                "station_id"=> $station?->id ?? null,
                "branch_id" => $branch?->id ?? null,
                "home_branch_id"  => $homeBranch?->id ?? null,
                "type"=> "agency",
                "agency_id" => $employee["agency_id"],
                "status" => $employee["employment_status"],
            ]);

            EducationHistory::create([
              "employee_id" => $emp->id,
              "employee_number" => $emp->employee_number,
              "college" => $employee["college"],
              "college_address" => $employee["college_address"],
              "course" => $employee["course"],
              "college_date_started" => $employee["cdate_started"],
              "college_date_graduated" => $employee["cdate_graduated"],
              "highschool" => $employee["highschool"],
              "highschool_address" => $employee["highschool_address"],
              "highschool_date_started" => $employee["hdate_started"],
              "highschool_date_graduated" => $employee["hdate_graduated"],
            ]);
        }
    }
}
