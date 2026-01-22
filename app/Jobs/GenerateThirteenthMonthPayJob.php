<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Reports\ReportsThirteenthMonthPayExport;
use App\Models\Payroll;
use App\Models\PayrollList;
use App\Models\ThirteenthMonthPay;
use App\Models\ThirteenthMonthFiles;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use DB;

class GenerateThirteenthMonthPayJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $user;
    protected $year;
    protected $payroll;

    public function __construct($user, $year, $payroll)
    {
        $this->user = $user;
        $this->year = $year;
        $this->payroll = $payroll;
    }

    public function handle()
    {
        $userId = User::find($this->user);

        $payrolls = Payroll::whereIn('id', $this->payroll)->get();
        $selectedYear = $this->year;

        $fileName = "13TH-MONTH-PAY-{$selectedYear}--" . now()->format('Y-m-d_His') .".xlsx";
        $disk = Storage::disk("hr_system_folder");
        $directory = "13th Month Files";

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        DB::beginTransaction();

        try {

            $thirteenthMonthFile = ThirteenthMonthFiles::create([
                'year' => $this->year,
                'month' => null,
                'type' => 'thirteenth_month_pay',
                'file_name' => $fileName,
                'file_path' => "{$directory}/{$fileName}",
                'generated_by' => $userId->id,
            ]);

            $currentYear = $this->year;
            $previousYear = $currentYear - 1;

            $payrollsByEmployee = $payrolls->groupBy('employee_id');

            $thirteenthMonthsData = new Collection();

            foreach ($payrollsByEmployee as $employeeId => $employeePayrolls) {

                $employee = $employeePayrolls->first()->employee;

                $december_2nd_cutoff = 0;
                $january_1st_cutoff = 0;
                $january_2nd_cutoff = 0;
                $february_1st_cutoff = 0;
                $february_2nd_cutoff = 0;
                $march_1st_cutoff = 0;
                $march_2nd_cutoff = 0;
                $april_1st_cutoff = 0;
                $april_2nd_cutoff = 0;
                $may_1st_cutoff = 0;
                $may_2nd_cutoff = 0;
                $june_1st_cutoff = 0;
                $june_2nd_cutoff = 0;
                $july_1st_cutoff = 0;
                $july_2nd_cutoff = 0;
                $august_1st_cutoff = 0;
                $august_2nd_cutoff = 0;
                $september_1st_cutoff = 0;
                $september_2nd_cutoff = 0;
                $october_1st_cutoff = 0;
                $october_2nd_cutoff = 0;
                $november_1st_cutoff = 0;
                $november_2nd_cutoff = 0;
                $december_1st_cutoff = 0;

                $daily_rate = 0;

                foreach ($employeePayrolls as $payroll) {
                    $daily_rate = $payroll['daily_rate'];

                    $salary = $payroll['salary'];
                    $tardiness = $payroll['tardiness_amount'];
                    $undertime = $payroll['undertime_amount'];

                    $start_date = $payroll['start_date'];
                    $end_date   = $payroll['end_date'];

                    $payrollList = PayrollList::where('employee_id', $employeeId)->first();
                    $keepPay = str_starts_with($employee->employee_number, 'M') 
                    || in_array($employee->employee_number, ['948', '0007413']);

                    $holdedCutoffs = json_decode($payrollList->holded_cutoffs ?? '[]', true);

                    $cutoffKey = "{$start_date}-{$end_date}";
                    $isHold = in_array($cutoffKey, $holdedCutoffs, true);

                    if ($start_date === "{$previousYear}-12-11" && $end_date === "{$previousYear}-12-25") {
                        if (!$isHold || $keepPay) {
                            $december_2nd_cutoff += $salary;
                            $december_2nd_cutoff -= $tardiness;
                            $december_2nd_cutoff -= $undertime;
                        } else {
                            $december_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$previousYear}-12-26" && $end_date === "{$currentYear}-01-10") {
                        if (!$isHold || $keepPay) {
                            $january_1st_cutoff += $salary;
                            $january_1st_cutoff -= $tardiness;
                            $january_1st_cutoff -= $undertime;
                        } else {
                            $january_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-01-11" && $end_date === "{$currentYear}-01-25") {
                        if (!$isHold || $keepPay) {
                            $january_2nd_cutoff += $salary;
                            $january_2nd_cutoff -= $tardiness;
                            $january_2nd_cutoff -= $undertime;
                        } else {
                            $january_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-01-26" && $end_date === "{$currentYear}-02-10") {
                        if (!$isHold || $keepPay) {
                            $february_1st_cutoff += $salary;
                            $february_1st_cutoff -= $tardiness;
                            $february_1st_cutoff -= $undertime;
                        } else {
                            $february_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-02-11" && $end_date === "{$currentYear}-02-25") {
                        if (!$isHold || $keepPay) {
                            $february_2nd_cutoff += $salary;
                            $february_2nd_cutoff -= $tardiness;
                            $february_2nd_cutoff -= $undertime;
                        } else {
                            $february_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-02-26" && $end_date === "{$currentYear}-03-10") {
                        if (!$isHold || $keepPay) {
                            $march_1st_cutoff += $salary;
                            $march_1st_cutoff -= $tardiness;
                            $march_1st_cutoff -= $undertime;
                        } else {
                            $march_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-03-11" && $end_date === "{$currentYear}-03-25") {
                        if (!$isHold || $keepPay) {
                            $march_2nd_cutoff += $salary;
                            $march_2nd_cutoff -= $tardiness;
                            $march_2nd_cutoff -= $undertime;
                        } else {
                            $march_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-03-26" && $end_date === "{$currentYear}-04-10") {
                        if (!$isHold || $keepPay) {
                            $april_1st_cutoff += $salary;
                            $april_1st_cutoff -= $tardiness;
                            $april_1st_cutoff -= $undertime;
                        } else {
                            $april_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-04-11" && $end_date === "{$currentYear}-04-25") {
                        if (!$isHold || $keepPay) {
                            $april_2nd_cutoff += $salary;
                            $april_2nd_cutoff -= $tardiness;
                            $april_2nd_cutoff -= $undertime;
                        } else {
                            $april_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-04-26" && $end_date === "{$currentYear}-05-10") {
                        if (!$isHold || $keepPay) {
                            $may_1st_cutoff += $salary;
                            $may_1st_cutoff -= $tardiness;
                            $may_1st_cutoff -= $undertime;
                        } else {
                            $may_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-05-11" && $end_date === "{$currentYear}-05-25") {
                        if (!$isHold || $keepPay) {
                            $may_2nd_cutoff += $salary;
                            $may_2nd_cutoff -= $tardiness;
                            $may_2nd_cutoff -= $undertime;
                        } else {
                            $may_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-05-26" && $end_date === "{$currentYear}-06-10") {
                        if (!$isHold || $keepPay) {
                            $june_1st_cutoff += $salary;
                            $june_1st_cutoff -= $tardiness;
                            $june_1st_cutoff -= $undertime;
                        } else {
                            $june_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-06-11" && $end_date === "{$currentYear}-06-25") {
                        if (!$isHold || $keepPay) {
                            $june_2nd_cutoff += $salary;
                            $june_2nd_cutoff -= $tardiness;
                            $june_2nd_cutoff -= $undertime;
                        } else {
                            $june_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-06-26" && $end_date === "{$currentYear}-07-10") {
                        if (!$isHold || $keepPay) {
                            $july_1st_cutoff += $salary;
                            $july_1st_cutoff -= $tardiness;
                            $july_1st_cutoff -= $undertime;
                        } else {
                            $july_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-07-11" && $end_date === "{$currentYear}-07-25") {
                        if (!$isHold || $keepPay) {
                            $july_2nd_cutoff += $salary;
                            $july_2nd_cutoff -= $tardiness;
                            $july_2nd_cutoff -= $undertime;
                        } else {
                            $july_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-07-26" && $end_date === "{$currentYear}-08-10") {
                        if (!$isHold || $keepPay) {
                            $august_1st_cutoff += $salary;
                            $august_1st_cutoff -= $tardiness;
                            $august_1st_cutoff -= $undertime;
                        } else {
                            $august_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-08-11" && $end_date === "{$currentYear}-08-25") {
                        if (!$isHold || $keepPay) {
                            $august_2nd_cutoff += $salary;
                            $august_2nd_cutoff -= $tardiness;
                            $august_2nd_cutoff -= $undertime;
                        } else {
                            $august_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-08-26" && $end_date === "{$currentYear}-09-10") {
                        if (!$isHold || $keepPay) {
                            $september_1st_cutoff += $salary;
                            $september_1st_cutoff -= $tardiness;
                            $september_1st_cutoff -= $undertime;
                        } else {
                            $september_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-09-11" && $end_date === "{$currentYear}-09-25") {
                        if (!$isHold || $keepPay) {
                            $september_2nd_cutoff += $salary;
                            $september_2nd_cutoff -= $tardiness;
                            $september_2nd_cutoff -= $undertime;
                        } else {
                            $september_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-09-26" && $end_date === "{$currentYear}-10-10") {
                        if (!$isHold || $keepPay) {
                            $october_1st_cutoff += $salary;
                            $october_1st_cutoff -= $tardiness;
                            $october_1st_cutoff -= $undertime;
                        } else {
                            $october_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-10-11" && $end_date === "{$currentYear}-10-25") {
                        if (!$isHold || $keepPay) {
                            $october_2nd_cutoff += $salary;
                            $october_2nd_cutoff -= $tardiness;
                            $october_2nd_cutoff -= $undertime;
                        } else {
                            $october_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-10-26" && $end_date === "{$currentYear}-11-10") {
                        if (!$isHold || $keepPay) {
                            $november_1st_cutoff += $salary;
                            $november_1st_cutoff -= $tardiness;
                            $november_1st_cutoff -= $undertime;
                        } else {
                            $november_1st_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-11-11" && $end_date === "{$currentYear}-11-25") {
                        if (!$isHold || $keepPay) {
                            $november_2nd_cutoff += $salary;
                            $november_2nd_cutoff -= $tardiness;
                            $november_2nd_cutoff -= $undertime;
                        } else {
                            $november_2nd_cutoff = 0;
                        }
                    }

                    if ($start_date === "{$currentYear}-11-26" && $end_date === "{$currentYear}-12-10") {
                        if (!$isHold || $keepPay) {
                            $december_1st_cutoff += $salary;
                            $december_1st_cutoff -= $tardiness;
                            $december_1st_cutoff -= $undertime;
                        } else {
                            $december_1st_cutoff = 0;
                        }
                    }
                }    

                $scheduleTypeName = optional($employee->schedule?->schedule_type)->name;

                $baseRegularDays = $scheduleTypeName === "Compressed Schedule" ? 11 : 13;

                $december1stCutoff = "{$currentYear}-11-26-{$currentYear}-12-10";

                $december_1st_cutoff = in_array($december1stCutoff, $holdedCutoffs, true) 
                ? ($keepPay ? $baseRegularDays * $daily_rate : 0)
                : $baseRegularDays * $daily_rate;

                $total = 
                    $december_2nd_cutoff +
                    $january_1st_cutoff +
                    $january_2nd_cutoff +
                    $february_1st_cutoff +
                    $february_2nd_cutoff +
                    $march_1st_cutoff +
                    $march_2nd_cutoff +
                    $april_1st_cutoff +
                    $april_2nd_cutoff +
                    $may_1st_cutoff +
                    $may_2nd_cutoff +
                    $june_1st_cutoff +
                    $june_2nd_cutoff +
                    $july_1st_cutoff +
                    $july_2nd_cutoff +
                    $august_1st_cutoff +
                    $august_2nd_cutoff +
                    $september_1st_cutoff +
                    $september_2nd_cutoff +
                    $october_1st_cutoff +
                    $october_2nd_cutoff +
                    $november_1st_cutoff +
                    $november_2nd_cutoff +
                    $december_1st_cutoff;

                $thirteenthMonth = ThirteenthMonthPay::create([
                    "thirteenth_month_file_id" => $thirteenthMonthFile->id,
                    "employee_id" => $employee->id,
                    "december_2nd_cutoff" => $december_2nd_cutoff,
                    "january_1st_cutoff" => $january_1st_cutoff,
                    "january_2nd_cutoff" => $january_2nd_cutoff,
                    "february_1st_cutoff" => $february_1st_cutoff,
                    "february_2nd_cutoff" => $february_2nd_cutoff,
                    "march_1st_cutoff" => $march_1st_cutoff,
                    "march_2nd_cutoff" => $march_2nd_cutoff,
                    "april_1st_cutoff" => $april_1st_cutoff,
                    "april_2nd_cutoff" => $april_2nd_cutoff,
                    "may_1st_cutoff" => $may_1st_cutoff,
                    "may_2nd_cutoff" => $may_2nd_cutoff,
                    "june_1st_cutoff" => $june_1st_cutoff,
                    "june_2nd_cutoff" => $june_2nd_cutoff,
                    "july_1st_cutoff" => $july_1st_cutoff,
                    "july_2nd_cutoff" => $july_2nd_cutoff,
                    "august_1st_cutoff" => $august_1st_cutoff,
                    "august_2nd_cutoff" => $august_2nd_cutoff,
                    "september_1st_cutoff" => $september_1st_cutoff,
                    "september_2nd_cutoff" => $september_2nd_cutoff,
                    "october_1st_cutoff" => $october_1st_cutoff,
                    "october_2nd_cutoff" => $october_2nd_cutoff,
                    "november_1st_cutoff" => $november_1st_cutoff,
                    "november_2nd_cutoff" => $november_2nd_cutoff,
                    "december_1st_cutoff" => $december_1st_cutoff ?? 0,
                    "year" => $currentYear,
                    "total" => $total, 
                    "net_claim" => $total / 12
                ]);

                $thirteenthMonthsData->push($thirteenthMonth);
            }

            Excel::store(
                new ReportsThirteenthMonthPayExport($thirteenthMonthsData),
                "{$directory}/{$fileName}",
                "hr_system_folder"
            );

            if (!$disk->exists("{$directory}/{$fileName}")) {
                throw new \Exception('Excel file was not generated');
            }

            DB::commit();

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Generate 13th month pay failed', [
                'error' => $e->getMessage(),
                'year' => $this->year,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
