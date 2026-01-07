<?php

namespace App\Jobs;

use App\Exports\Reports\ReportsThirteenthMonthMonthlyAllocationExport;
use App\Models\Branch;
use App\Models\BranchesMonthlyAllocation;
use App\Models\EmployeesMonthlyAllocation;
use App\Models\Payroll;
use App\Models\PayrollList;
use App\Models\ThirteenthMonthFiles;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;
use DB;

class GenerateThirteenthMonthMonthlyAllocationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $user;
    protected $month;
    protected $year;
    protected $payroll;

    public function __construct($user, $month, $year, $payroll)
    {
        $this->user = $user;
        $this->month = $month;
        $this->year = $year;
        $this->payroll = $payroll;
    }

    public function handle()
    {
        $user = User::find($this->user);
        $employee_id = $user->employee->id ?? null;

        $payrolls = Payroll::whereIn('id', $this->payroll)->get();

        $months = [
            1 => 'JANUARY',
            2 => 'FEBRUARY',
            3 => 'MARCH',
            4 => 'APRIL',
            5 => 'MAY',
            6 => 'JUNE',
            7 => 'JULY',
            8 => 'AUGUST',
            9 => 'SEPTEMBER',
            10 => 'OCTOBER',
            11 => 'NOVEMBER',
            12 => 'DECEMBER'
        ];

        $month = $this->month;
        $year = $this->year;

        $selectedMonth = $months[$month];

        $fileName = "13TH-MONTH-MONTHLY-ALLOCATION-{$selectedMonth}-{$year}--" . now()->format('Y-m-d_His') .".xlsx";
        $directory = "13th Month Files";
        $disk      = Storage::disk("hr_system_folder");

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        DB::beginTransaction();

        try {

            $thirteenthMonthFile = ThirteenthMonthFiles::create([
                'year' => $this->year,
                'month' => $this->month,
                'type' => 'thirteenth_month_monthly_allocation',
                'file_name' => $fileName,
                'file_path' => "{$directory}/{$fileName}",
                'generated_by' => $employee_id,
            ]);

            $employeesMonthlyAllocationData = new Collection();

            foreach ($payrolls->groupBy('employee_id') as $employeePayrolls) {

                $employee = $employeePayrolls->first()->employee;
                $sorted   = $employeePayrolls->sortBy('start_date')->values();

                $first = $sorted->first();
                $last  = $sorted->last();

                $first_cutoff  = "{$first->start_date}-{$first->end_date}";
                $second_cutoff = "{$last->start_date}-{$last->end_date}";

                $basic_pay_1st_cutoff = 0;
                $basic_pay_2nd_cutoff = 0;

                foreach ($employeePayrolls as $payroll) {
                    $salary    = (float) str_replace(',', '', $payroll->salary);
                    $tardiness = (float) str_replace(',', '', $payroll->tardiness_amount);
                    $undertime = (float) str_replace(',', '', $payroll->undertime_amount);

                    $computedPay = $salary - $tardiness - $undertime;
                    $cutoffKey  = "{$payroll->start_date}-{$payroll->end_date}";

                    if ($cutoffKey === $first_cutoff) {
                        $basic_pay_1st_cutoff += $computedPay;
                    } elseif ($cutoffKey === $second_cutoff) {
                        $basic_pay_2nd_cutoff += $computedPay;
                    }
                }

                $total = $basic_pay_1st_cutoff + $basic_pay_2nd_cutoff;

                $branchShortName = $last
                ? optional(Branch::find($last->timekeeping_branch_id))->short_name
                : optional(Branch::find($first->timekeeping_branch_id))->short_name;

                $employeesMonthlyAllocationData->push(
                    EmployeesMonthlyAllocation::create([
                        'thirteenth_month_file_id' => $thirteenthMonthFile->id,
                        'employee_id' => $employee->id,
                        'branch_short_name' => $branchShortName,
                        'basic_pay_1st_cutoff' => $basic_pay_1st_cutoff,
                        'basic_pay_2nd_cutoff' => $basic_pay_2nd_cutoff,
                        'total' => $total,
                        'net_claim_13th_month' => round($total / 12, 2),
                        'net_claim_pay_leave' => round($total * 0.06, 2),
                        'net_claim_additional' => round($total * 0.035, 2),
                    ])
                );
            }

            $branchesMonthlyAllocationData = new Collection();

            foreach ($employeesMonthlyAllocationData->groupBy('branch_short_name') as $branchShort => $items) {

                $branchesMonthlyAllocationData->push(
                    BranchesMonthlyAllocation::create([
                        'thirteenth_month_file_id' => $thirteenthMonthFile->id,
                        'branch_id' => optional(
                            Branch::where('short_name', $branchShort)->first()
                        )->id,
                        'branch_net_claim_13th_month' => $items->sum('net_claim_13th_month'),
                        'branch_net_claim_pay_leave' => $items->sum('net_claim_pay_leave'),
                        'branch_net_claim_additional' => $items->sum('net_claim_additional'),
                        'branch_total' => $items->sum('net_claim_13th_month')
                            + $items->sum('net_claim_pay_leave')
                            + $items->sum('net_claim_additional'),
                    ])
                );
            }

            Excel::store(
                new ReportsThirteenthMonthMonthlyAllocationExport(
                    $employeesMonthlyAllocationData,
                    $branchesMonthlyAllocationData
                ),
                "{$directory}/{$fileName}",
                "hr_system_folder"
            );

            if (!$disk->exists("{$directory}/{$fileName}")) {
                throw new \Exception('Excel file was not generated');
            }

            DB::commit();

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Generate 13th month monthly allocation failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
