<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateBiotimeEmployees extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $getEmployeeList = "http://120.28.114.47:9090/personnel/api/employees/";
        $getDepartmentList = "http://120.28.114.47:9090/personnel/api/departments/";
        $getPositionsList = "http://120.28.114.47:9090/personnel/api/positions/";
        $user = "itdept";
        $password = "classic1950";
        $branches = [];
        $positions = [];
        
        $dept = Http::withBasicAuth($user, $password)
        ->withHeaders([
            "Accept" => "application/json",
            "Content-Type" => "application/json"
        ])
        ->timeout(60)
        ->get($getDepartmentList, [
            "page_size" => 100000
        ]);

        $post = Http::withBasicAuth($user, $password)
        ->withHeaders([
            "Accept" => "application/json",
            "Content-Type" => "application/json"
        ])
        ->timeout(60)
        ->get($getPositionsList, [
            "page_size" => 100000
        ]);

        if ($dept->successful()) {
            $deptData = $dept->json();
            $branches = $deptData["data"];
        }

        if ($post->successful()) {
            $postData = $post->json();
            $positions = $postData["data"];
        }

        $response = Http::withBasicAuth($user, $password)
        ->withHeaders([
            "Accept" => "application/json",
            "Content-Type" => "application/json"
        ])
        ->timeout(60)
        ->get($getEmployeeList, [
            "page_size" => 100000
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $biotimeEmployees = $data["data"];
            
            $failedEmployees = [];
            foreach($biotimeEmployees as $biotimeEmployee) {
                $employee = Employee::where("biometric_number", $biotimeEmployee["emp_code"])->first();

                if ($employee) {
                    try {
                        $duplicatedEmploye = Employee::where("biometric_number", $biotimeEmployee["emp_code"])->get();
                        if ($duplicatedEmploye->count() > 1) {
                            $employee = Employee::where("biometric_number", $biotimeEmployee["emp_code"])
                            ->where("status", "active")->first();
                        }
                        $updateEmployeeApi = "http://120.28.114.47:9090/personnel/api/employees/{$biotimeEmployee['id']}/";;
                        $selfPassword = Carbon::parse($employee->birthday)->format('mdY');

                        $dept = array_filter($branches, function ($branch) use ($employee) {
                            return $branch['dept_name'] === $employee->current_branch->short_name;
                        });
                        $dept = array_values($dept);

                        $position = array_filter($positions, function ($pos) use ($employee) {
                            return $pos['position_name'] === $employee->current_position->description;
                        });
                        $position = array_values( $position);

                        $updateEmployee = Http::withBasicAuth($user, $password)
                        ->withHeaders([
                            "Accept" => "application/json",
                            "Content-Type" => "application/json"
                        ])
                        ->timeout(60)
                        ->patch($updateEmployeeApi, [
                            "first_name" => $employee->first_name,
                            "last_name" => $employee->last_name,
                            "email" => $employee->email,
                            "birthday" => $employee->birthday ?? null,
                            "mobile" => $employee->contact_number ?? null,
                            "hire_date" => $employee->date_hired ?? null,
                            "address" => $employee->address ?? null,
                            "self_password" => $selfPassword ?? null,
                            "position" => $position[0]["id"] ?? null,
                            "department" => $dept[0]["id"] ?? 1
                        ]);

                        
                        if ($updateEmployee->successful()) {
                            Log::info("UPDATED SUCCESSFULLY: " .$updateEmployee["first_name"]." - ".$updateEmployee["last_name"]." - ".$employee->biometric_number. " - ". $selfPassword);
                        }
                    } catch (Exception $e) {
                        $failedEmployee[] = $biotimeEmployee;
                    }
                }
            }

            Log::info("==============================RETRYING FAILED EMPLOYEE UPDATES=======================================");
            foreach($failedEmployees as $failedEmployee) {
                $employee = Employee::where("biometric_number", $failedEmployee["emp_code"])->first();

                if ($employee) {
                    try {
                        $duplicatedEmploye = Employee::where("biometric_number", $biotimeEmployee["emp_code"])->get();
                        if ($duplicatedEmploye->count() > 1) {
                            $employee = Employee::where("biometric_number", $biotimeEmployee["emp_code"])
                            ->where("status", "active")->first();
                        }

                        $updateEmployeeApi = "http://120.28.114.47:9090/personnel/api/employees/{$failedEmployee['id']}/";;
                        $selfPassword = Carbon::parse($employee->birthday)->format('mdY');

                        $dept = array_filter($branches, function ($branch) use ($employee) {
                            return $branch['dept_name'] === $employee->current_branch->short_name;
                        });
                        $dept = array_values($dept);

                        $position = array_filter($positions, function ($pos) use ($employee) {
                            return $pos['position_name'] === $employee->current_position->description;
                        });
                        $position = array_values($position);

                        $updateEmployee = Http::withBasicAuth($user, $password)
                        ->withHeaders([
                            "Accept" => "application/json",
                            "Content-Type" => "application/json"
                        ])
                        ->timeout(60)
                        ->patch($updateEmployeeApi, [
                            "first_name" => $employee->first_name,
                            "last_name" => $employee->last_name,
                            "email" => $employee->email,
                            "birthday" => $employee->birthday ?? null,
                            "mobile" => $employee->contact_number ?? null,
                            "hire_date" => $employee->date_hired ?? null,
                            "address" => $employee->address ?? null,
                            "self_password" => $selfPassword ?? null,
                            "position" => $position[0]["id"] ?? null,
                            "departmennt" => $dept[0]["id"] ?? 1
                        ]);

                        if ($updateEmployee->successful()) {
                            Log::info("UPDATED SUCCESSFULLY: " .$updateEmployee["first_name"]." - ".$updateEmployee["last_name"]." - ".$employee->biometric_number. " - ". $selfPassword);
                        }
                    } catch (Exception $e) {
                        Log::error($e);
                    }
                }
            }
        }
    }
}
