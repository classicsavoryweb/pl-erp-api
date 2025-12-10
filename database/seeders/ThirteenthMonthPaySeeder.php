<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ThirteenthMonthPay;
use App\Models\ThirteenthMonthFiles;
use Illuminate\Database\Seeder;

class ThirteenthMonthPaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = public_path('jsons/payroll/thirteenth_month.json');
        $jsonData = file_get_contents($jsonPath);
        $rawData = json_decode($jsonData, true);

        $thirteenthMonthsByYear = [];
        foreach ($rawData['tbl_13thmonth_payslip'] as $row) {
            $thirteenthMonthsByYear[$row['year']][] = $row;
        }

        foreach ($thirteenthMonthsByYear as $year => $yearRows) {

            $file = ThirteenthMonthFiles::create([
                "year"         => $year,
                "type"         => 'thirteenth_month_pay',
                "file_name"    => '13TH-MONTH-PAY-' . $year,
                "file_path"    => "13th Month Pay/13TH-MONTH-PAY-$year",
                "generated_by" => null,
            ]);

            $cutoffKeys = [
                "dec_2nd","jan_1st","jan_2nd","feb_1st","feb_2nd",
                "march_1st","march_2nd","april_1st","april_2nd",
                "may_1st","may_2nd","june_1st","june_2nd",
                "july_1st","july_2nd","aug_1st","aug_2nd",
                "sept_1st","sept_2nd","oct_1st","oct_2nd",
                "nov_1st","nov_2nd","dec_1st"
            ];

            foreach ($yearRows as $row) {

                $employee = Employee::where("employee_number", $row["employee_id2"])->first();

                if (!$employee){
                    continue;
                }

                $total = 0.00;
                foreach ($cutoffKeys as $key) {
                    $total += floatval($row[$key]);
                }

                ThirteenthMonthPay::create([
                    "thirteenth_month_file_id" => $file->id,
                    "employee_id" => $employee->id,

                    "december_2nd_cutoff" => floatval($row["dec_2nd"]),
                    "january_1st_cutoff"  => floatval($row["jan_1st"]),
                    "january_2nd_cutoff"  => floatval($row["jan_2nd"]),
                    "february_1st_cutoff" => floatval($row["feb_1st"]),
                    "february_2nd_cutoff" => floatval($row["feb_2nd"]),
                    "march_1st_cutoff"    => floatval($row["march_1st"]),
                    "march_2nd_cutoff"    => floatval($row["march_2nd"]),
                    "april_1st_cutoff"    => floatval($row["april_1st"]),
                    "april_2nd_cutoff"    => floatval($row["april_2nd"]),
                    "may_1st_cutoff"      => floatval($row["may_1st"]),
                    "may_2nd_cutoff"      => floatval($row["may_2nd"]),
                    "june_1st_cutoff"     => floatval($row["june_1st"]),
                    "june_2nd_cutoff"     => floatval($row["june_2nd"]),
                    "july_1st_cutoff"     => floatval($row["july_1st"]),
                    "july_2nd_cutoff"     => floatval($row["july_2nd"]),
                    "august_1st_cutoff"   => floatval($row["aug_1st"]),
                    "august_2nd_cutoff"   => floatval($row["aug_2nd"]),
                    "september_1st_cutoff"=> floatval($row["sept_1st"]),
                    "september_2nd_cutoff"=> floatval($row["sept_2nd"]),
                    "october_1st_cutoff"  => floatval($row["oct_1st"]),
                    "october_2nd_cutoff"  => floatval($row["oct_2nd"]),
                    "november_1st_cutoff" => floatval($row["nov_1st"]),
                    "november_2nd_cutoff" => floatval($row["nov_2nd"]),
                    "december_1st_cutoff" => floatval($row["dec_1st"]),

                    "year"      => $row["year"],
                    "total"     => $total,
                    "net_claim" => floatval($row["total_amount"]),
                ]);
            }
        }
    }
}
