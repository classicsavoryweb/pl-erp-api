<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Http\Controllers\Admin\ActivityLogsController;
use App\Http\Services\TimeKeepingService;
use App\Models\BiotimeLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollList;
use App\Models\ScheduleTime;
use App\Models\TimeKeeping;
use App\Models\TimeKeepingSummary;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call(RoleSeeder::class);
        // $this->call(PermissionSeeder::class);
        // $this->call(AssignPermissionToAdminSeeder::class);
        // $this->call(AdminSeeder::class);
        // $this->call(HRRoleSeeder::class);
        // $this->call(MaintenanceSeeder::class);
        // $this->call(EmployeeRecordSeeder::class);
        // $this->call(EntrySeeder::class);

        // $timekeepingService = new TimeKeepingService();

        // $timeKeepings = TimeKeeping::where("date", ">", "2025-11-25")->get();

        // foreach ($timeKeepings as $timeKeep) {
        //     $schedule = ScheduleTime::where("employee_id", $timeKeep->employee_id)->where("date", $timeKeep->date)->first();
        //     $timekeepingService->computePayment($timeKeep, $schedule);
        // }

        // Incentive LEAVE
        // $employees = Employee::with(["current_branch","schedule"])->get();

        // foreach ($employees as $employee) {
        //     $incentiveLeave = "No";
        //     $flexi = "No";
        //     if ( in_array($employee->position_id, [12,86,139,53,63,136]) ) {
        //         $incentiveLeave = "Yes";
        //         $flexi = "Yes";
        //     }

        //     if ($employee->current_branch?->type == "Head Office") {
        //         $flexi = "Yes";
        //     }

        //     $employee->schedule->update([
        //         "incentive_leave" => $incentiveLeave,
        //         "flexible_schedule" => $flexi
        //     ]);
        // }

        // TimeKeeping::whereHas('employee', function ($query) {
        //     $query->where("type", "Agency");
        // })->delete();

        // TimeKeepingSummary::whereHas('employee', function ($query) {
        //     $query->where("type", "Agency");
        // })->delete();

        // $payrollList = PayrollList::with(["employee"])->get();

        // foreach ($payrollList as $list) {
        //     $employee = $list->employee;
        //     if ($employee->status == "inactive") {
        //         Log::info($employee->full_name);
        //         $date = Carbon::parse($employee->separation_date);
        //         $day = 11;

        //         if ($date->day >= 11 && $date->day <= 25) {
        //             $day = 25;
        //         }


        //         $date = Carbon::parse($employee->separation_date)->day($day)->startOfDay()->format("Y-m-d H:i:s");
        //         Log::info("SEPARATION DATE ". $employee->separation_date);
        //         Log::info("HOLDING DATE ". $date);

        //         // $date = Carbon::parse($employee->separation_date)->startOfDay()->format("Y-m-d H:i:s"); 
        //         $list->update([
        //             "holded_at" => $date,
        //             "is_hold" => true
        //         ]);
        //     } 

        // }

        // $deductions = Activity::where("log_name", "Employee Deductions")
        // ->where("event", "deleted")
        // ->get();

        // foreach ($deductions as $ded) {
        //     $array = json_decode($ded->properties, true);
            
        //     $data = collect($array["old"])->except(['id', "created_at", "updated_at"])->toArray();

        //     // Log::info($array["old"]);
        //     EmployeeDeduction::create($data);
        // }

        $jsonPath = public_path("jsons/payroll/employee_deductions/sfi_deductions.json");

        $jsonData = file_get_contents($jsonPath);
        $data = json_decode($jsonData, true);

        foreach ($data as $deduction) {
            $employee = Employee::where("employee_number", $deduction["emp_id"])->first();
            $branch = Branch::where("branch_number", $deduction["branch"])->first();

            if (!$employee) {
                continue;
            }
            // Log::info("======================");
            // Log::info($employee->full_name);
            // Log::info($deduction);

            $deductionIdMap = [
                '00000011' => 10,
                '00000012' => 11,
                '00000013' => 12,
                '00000014' => 13,
                '00000015' => 14,
                '00000016' => 15,
                '00000017' => 16,
                '00000018' => 17,
                '00000019' => 18,
            ];

            if (array_key_exists($deduction["deduction_id2"], $deductionIdMap)) {
                $deduction["deduction_id2"] = $deductionIdMap[$deduction["deduction_id2"]];
            } else {
                $deduction["deduction_id2"] = ltrim($deduction["deduction_id2"], '0');
            }

            EmployeeDeduction::updateOrCreate([
                "employee_id" => $employee ? $employee->id : null,
                "amount" => floatval(str_replace(',', '', $deduction["amount"])),
                "date_created" => $deduction["date_created"],
                "remarks" => $deduction["remarks"],
            ],[
                "deduction_id" => $deduction["deduction_id2"],
                "reference_doc" => $deduction["reference_doc"],
                "months" => $deduction["months"],
                "source_of_fund" => $deduction["source_of_fund"],
                "start_date" => $deduction["start_date"],
                "end_date" => $deduction["end_date"],
                "monthly_amortization" => floatval(str_replace(',', '', $deduction["monthly_amortization"])),
                "balance" => floatval(str_replace(',', '', $deduction["balance"])),
                "debit_id" => $deduction["debit_account_id"],
                "credit_id" => $deduction["credit_account_id"],
                "branch_id" => $branch ? $branch->id : null,
                "block_payment" => $deduction["block_payment"],
                "approved_by" => $deduction["approved_by"],
                "approved_date" => $deduction["approved_date"],
                "approved_status" => $deduction["approved_status"],
                "deduction_ref" => $deduction["deduction_ref"],
                "deduction_status" => $deduction["deduction_status"],
                "created_by" => $deduction["created_by"],
                "end_loan_reason" => $deduction["endloan_reason"],
                "end_loan_status" => $deduction["endloan_status"],
            ]);
        }


        // $jsonPath = public_path("jsons/payroll/plist.json");

        // $jsonData = file_get_contents($jsonPath);
        // $data = json_decode($jsonData, true);

        // foreach ($data['tbl_payroll_list'] as $d) {
            //         {
            //   "payroll_id": "1",
            //   "employee_id": "0000002",
            //   "resigned_date": "2021-06-01",
            //   "f_name": "LEVEI",
            //   "m_name": "FELICIANO",
            //   "l_name": "FLORES",
            //   "short_name": "LEVEI",
            //   "address": "Morales St. Pob. West, Sta. Ignacia, Tarlac",
            //   "gender": "Male",
            //   "b_day": "1976-01-05",
            //   "position": "Senior Roving Head Cook",
            //   "date_hired": "2007-07-18",
            //   "work_status": "Resigned",
            //   "status_date": "2007-07-18",
            //   "hbranch_id": "SFI00089",
            //   "hbranch_name": "SM San Lazaro",
            //   "tax_status": "S",
            //   "sss": "33-2372369-2",
            //   "pagibig": "1210-3352-9837",
            //   "philhealth": "02-050027527-9",
            //   "tin": "231-540-141-000",
            //   "with_complete_docs": "1",
            //   "region_name": "NCR - National Capital Region",
            //   "region_rate": "695.00",
            //   "pay_type": "MONTHLY",
            //   "monthly_rate": "17,250.00",
            //   "daily_rate": "663.46",
            //   "with_cola": "0",
            //   "cash": "1",
            //   "bank": "0",
            //   "bank_account_no": "",
            //   "vendor_id": "0000002",
            //   "include_payroll": "0",
            //   "for_rate": null,
            //   "account_id": null
            // },
            // $employee = Employee::where("employee_number", $d["employee_id"])->first();

            // if (!$employee) {
            //     continue;
            // }
            
            // $isExisting = PayrollList::where("employee_id", $employee->id)->first();

            // if ($isExisting) {
            //     PayrollList::where("employee_id", $employee->id)->update([
            //         "is_included" => $d['include_payroll']
            //     ]);
            // } else {
            //     PayrollList::create([
            //         "employee_id" => $employee->id,
            //         "is_included" => $d['include_payroll'],
            //         "is_bank" => $d['cash'] == 1 ? 0 : 1,
            //     ]);
            // }
        // }

    }
}
