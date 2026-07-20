<?php

namespace App\Imports;

use App\Models\Bir2316;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class Bir2316Import implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public int $imported = 0;

    public function __construct(private int $year, private string $scheduleType)
    {
    }

    public function sheets(): array
    {
        
        return [1 => $this];
    }

    public function collection(Collection $collection)
    {
        $rows = [];
        $errors = [];
        $now = now();

        foreach ($collection as $index => $row) {
            $employeeNumber = trim((string) ($row['employee_id'] ?? ''));

            if ($employeeNumber === '') {
                continue;
            }

            $employee = Employee::where('employee_number', $employeeNumber)->first();

            if (!$employee) {
                $name = trim(trim((string) ($row['last_name'] ?? '')) . ', ' . trim((string) ($row['first_name'] ?? '')), ', ');
                $label = $name !== '' && $name !== ',' ? " ({$name})" : '';
                $errors[] = 'Row ' . ($index + 2) . ": employee ID \"{$employeeNumber}\"{$label} has no matching employee record.";

                continue;
            }

            $rows[] = [
                'year' => $this->year,
                'employee_id' => $employee->id,
                'schedule_type' => $this->scheduleType,
                'tin' => $this->digitsOnly($row['tin'] ?? null, 9),
                'last_name' => $this->clean($row['last_name'] ?? null),
                'first_name' => $this->clean($row['first_name'] ?? null),
                'middle_name' => $this->clean($row['middle_name'] ?? null),
                'rdo_code' => $this->clean($row['rdo_emp'] ?? null),
                'registered_address' => $this->clean($row['reg_address'] ?? null),
                'date_of_birth' => $this->excelDate($row['date_of_birth'] ?? null),
                'contact_number' => $this->clean($row['contact_number'] ?? null),
                'period_from' => $this->excelDate($row['from_date'] ?? null),
                'period_to' => $this->excelDate($row['to_date'] ?? null),
                'is_mwe' => $this->scheduleType === Bir2316::SCHEDULE_2,
                'smw_rate_per_day' => $this->numeric($row['per_day'] ?? 0),
                'smw_rate_per_month' => $this->numeric($row['per_month'] ?? 0),
                'gross_compensation' => $this->numeric($row['gross'] ?? 0),
                'less_non_taxable' => $this->numeric($row['non_tax'] ?? 0),
                'taxable_comp_present' => $this->numeric($row['tax_comp'] ?? 0),
                'previous_employer_taxable_income' => $this->numeric($row['add_tax'] ?? 0),
                'gross_taxable_comp' => $this->numeric($row['gross_tax'] ?? 0),
                'tax_due' => $this->numeric($row['tax_due'] ?? 0),
                'taxes_withheld_present' => $this->numeric($row['present_er'] ?? 0),
                'previous_employer_tax_withheld' => $this->numeric($row['previous_er'] ?? 0),
                'total_taxes_withheld' => $this->numeric($row['tax_withheld'] ?? 0),
                'basic_salary_mwe' => $this->numeric($row['basicsalary_non_tax'] ?? 0),
                'holiday_pay_mwe' => $this->numeric($row['holiday'] ?? 0),
                'overtime_pay_mwe' => $this->numeric($row['overtime'] ?? 0),
                'night_shift_diff_mwe' => $this->numeric($row['night_shift'] ?? 0),
                'hazard_pay_mwe' => $this->numeric($row['hazzard'] ?? 0),
                'thirteenth_month_pay' => $this->numeric($row['13th_month'] ?? 0),
                'de_minimis_benefits' => $this->numeric($row['de_minimis'] ?? 0),
                'sss_gsis_phic_hdmf_ee' => $this->numeric($row['sss_gsis'] ?? 0),
                'salaries_other_nontax' => $this->numeric($row['salaries_other'] ?? 0),
                'total_non_taxable' => $this->numeric($row['total_non_tax'] ?? 0),
                'basic_salary_taxable' => $this->numeric($row['basicsalary2_taxable'] ?? 0),
                'representation' => $this->numeric($row['representation'] ?? 0),
                'transportation' => $this->numeric($row['transportation'] ?? 0),
                'cola' => $this->numeric($row['cola'] ?? 0),
                'fixed_housing' => $this->numeric($row['fixed'] ?? 0),
                'others_taxable_a' => $this->numeric($row['42a'] ?? 0),
                'others_taxable_b' => $this->numeric($row['42b'] ?? 0),
                'commission' => $this->numeric($row['commision'] ?? 0),
                'profit_sharing' => $this->numeric($row['profit'] ?? 0),
                'fees' => $this->numeric($row['fees'] ?? 0),
                // Schedule 1 and Schedule 2 templates label this column differently.
                'taxable_13th_month_excess' => $this->numeric($row['taxable_13th_mo'] ?? $row['taxable'] ?? 0),
                'hazard_pay_taxable' => $this->numeric($row['hazard_pay'] ?? 0),
                'overtime_pay_taxable' => $this->numeric($row['overtime_pay'] ?? 0),
                'others_51a' => $this->numeric($row['49a'] ?? 0),
                'others_51b' => $this->numeric($row['49b'] ?? 0),
                'total_taxable_regular' => $this->numeric($row['total_tax_comp'] ?? 0),
                'five_percent_tax_credit' => $this->numeric($row['27_tax_credit'] ?? 0),
                'status' => Bir2316::STATUS_FINAL,
                'generated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($errors)) {
            $preview = array_slice($errors, 0, 10);
            $message = implode('; ', $preview);

            if (count($errors) > count($preview)) {
                $message .= '; and ' . (count($errors) - count($preview)) . ' more.';
            }

            throw new \RuntimeException($message);
        }

        Bir2316::where('year', $this->year)
            ->where('schedule_type', $this->scheduleType)
            ->delete();

        foreach (array_chunk($rows, 200) as $chunk) {
            Bir2316::insert($chunk);
        }

        $this->imported = count($rows);
    }

    private function numeric($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $stripped = str_replace(',', '', (string) $value);

        return is_numeric($stripped) ? (float) $stripped : 0.0;
    }

    private function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(['_x000D_', "\r", "\n"], ' ', (string) $value);

        return trim($value) ?: null;
    }

    private function digitsOnly($value, int $padTo): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return str_pad($digits, $padTo, '0', STR_PAD_LEFT);
    }

    private function excelDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
