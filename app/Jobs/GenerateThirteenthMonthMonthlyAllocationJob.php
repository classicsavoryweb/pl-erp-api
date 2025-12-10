<?php

namespace App\Jobs;

use App\Exports\Reports\ReportsThirteenthMonthMonthlyAllocationExport;
use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\BranchesMonthlyAllocation;
use App\Models\EmployeesMonthlyAllocation;
use App\Models\Payroll;
use App\Models\PayrollList;
use App\Models\ThirteenthMonthFiles;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use DB;

class GenerateThirteenthMonthMonthlyAllocationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $user;
    protected $payroll;

    public function __construct($user, $payroll)
    {
        $this->user = $user;
        $this->payroll = $payroll;
    }

    public function handle()
    {
        $user = User::find($this->user);
        $employee_id = $user->employee->id ?? null;

        $payrolls = Payroll::whereIn('id', $this->payroll)->get();

        $fileName = "13TH-MONTH-MONTHLY-ALLOCATION-" . now()->format('Y') . ".xlsx";
        $disk = Storage::disk("hr_system_folder");
        $directory = "13th Month Files";

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        try {
            DB::beginTransaction();

            $thirteenthMonthFile = ThirteenthMonthFiles::create([
                'year' => now()->year,
                'type' => 'thirteenth_month_monthly_allocation',
                'file_name' => $fileName,
                'file_path' => "{$directory}/{$fileName}",
                'generated_by' => $employee_id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create thirteenth month file', [
                'error' => $e->getMessage(),
            ]);
        }

        $payrollsByEmployee = $payrolls->groupBy('employee_id');

        $employeesMonthlyAllocationData = new Collection();

        foreach ($payrollsByEmployee as $employeeId => $employeePayrolls) {

            $employee = $employeePayrolls->first()->employee;

            $sortedPayrolls = $employeePayrolls->sortBy('start_date')->values();

            $firstPayroll = $sortedPayrolls->first();
            $lastPayroll  = $sortedPayrolls->last();

            $first_start = $firstPayroll['start_date'];
            $first_end   = $firstPayroll['end_date'];

            $second_start = $lastPayroll['start_date'];
            $second_end   = $lastPayroll['end_date'];

            $first_cutoff  = "{$first_start}-{$first_end}";
            $second_cutoff = "{$second_start}-{$second_end}";

            $basic_pay_1st_cutoff = 0;
            $basic_pay_2nd_cutoff = 0;

            foreach ($employeePayrolls as $payroll) {

                $salary    = floatval(str_replace(',', '', $payroll['salary']));
                $tardiness = floatval(str_replace(',', '', $payroll['tardiness_amount']));
                $undertime = floatval(str_replace(',', '', $payroll['undertime_amount']));

                $computedPay = $salary - $tardiness - $undertime;

                $cutoffKey = "{$payroll['start_date']}-{$payroll['end_date']}";

                if ($cutoffKey === $first_cutoff) {

                    $basic_pay_1st_cutoff += $computedPay;
                } elseif ($cutoffKey === $second_cutoff) {

                    $basic_pay_2nd_cutoff += $computedPay;
                }
            }
            
            $timeKeepingBranchId = $employeePayrolls->first()->timekeeping_branch_id;
            $branch = Branch::find($timeKeepingBranchId);

            $firstPayrollList = PayrollList::where('employee_id', $employee->id)
                ->where('holded_at', $first_cutoff)
                ->first();

            $secondPayrollList = PayrollList::where('employee_id', $employee->id)
                ->where('holded_at', $second_cutoff)
                ->first();

            $branchShortName = "";

            if ($secondPayrollList && $secondPayrollList->is_hold === null) {
                if ($firstPayrollList && $firstPayrollList->is_hold === null) {
                    $branchShortName = null;
                } else {
                    $branchShortName = $branch->short_name;
                } 
            } else if ($secondPayrollList && $secondPayrollList->is_hold === 1) {
                $branchShortName = 'HOLD';
            } else {
                $branchShortName = $branch->short_name;
            }

            $total = $basic_pay_1st_cutoff + $basic_pay_2nd_cutoff;
            $net_claim_13th_month = round($total / 12, 2);
            $net_claim_pay_leave  = round($total * 0.06, 2);
            $net_claim_additional = round($total * 0.035, 2);

            try {
                DB::beginTransaction();

                $employeesMonthlyAllocation = EmployeesMonthlyAllocation::create([
                    "thirteenth_month_file_id" => $thirteenthMonthFile->id,
                    "employee_id" => $employee->id,
                    "branch_short_name" => $branchShortName,
                    "basic_pay_1st_cutoff" => $basic_pay_1st_cutoff,
                    "basic_pay_2nd_cutoff" => $basic_pay_2nd_cutoff,
                    "total" => $total,
                    "net_claim_13th_month" => $net_claim_13th_month,
                    "net_claim_pay_leave" => $net_claim_pay_leave,
                    "net_claim_additional" => $net_claim_additional,
                ]);

                $employeesMonthlyAllocationData->push($employeesMonthlyAllocation);

                DB::commit();

            } catch (\Exception $e) {

                DB::rollBack();

                Log::error('Failed to create employee 13th month monthly allocation', [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                    'employeesMonthlyAllocation' => $employeesMonthlyAllocation 
                ]);
            }
        }

        $groupedByBranch = $employeesMonthlyAllocationData
        ->groupBy(function ($item) use ($payrolls) {
            $employeePayroll = $payrolls->where('employee_id', $item->employee_id)->first();
            return $employeePayroll ? $employeePayroll->timekeeping_branch_id : null;
        });

        $branchesMonthlyAllocationData = new Collection();

        foreach ($groupedByBranch as $branchId => $items) {
            $branch_net_claim_13th_month = $items->sum('net_claim_13th_month');
            $branch_net_claim_pay_leave  = $items->sum('net_claim_pay_leave');
            $branch_net_claim_additional = $items->sum('net_claim_additional');

            $branch_total = $branch_net_claim_13th_month +
                            $branch_net_claim_pay_leave +
                            $branch_net_claim_additional;

            try {
                DB::beginTransaction();

                $branchesMonthlyAllocation = BranchesMonthlyAllocation::create([
                    "thirteenth_month_file_id" => $thirteenthMonthFile->id,
                    "branch_id" => $branchId,
                    "branch_net_claim_13th_month" => $branch_net_claim_13th_month,
                    "branch_net_claim_pay_leave" => $branch_net_claim_pay_leave,
                    "branch_net_claim_additional" => $branch_net_claim_additional,
                    "branch_total" => $branch_total
                ]);

                $branchesMonthlyAllocationData->push($branchesMonthlyAllocation);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('Failed to create branch 13th month monthly allocation', [
                    'branch_id' => $branchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Excel::store(
            new ReportsThirteenthMonthMonthlyAllocationExport($employeesMonthlyAllocationData, $branchesMonthlyAllocationData),
            "{$directory}/{$fileName}",
            "hr_system_folder"
        );
    }
}
