<?php

namespace App\Exports\Compensation;

use App\Models\Rate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PayrollListExport implements FromCollection, WithHeadings, WithColumnFormatting
{
    protected Collection $payrollList;
    protected array $rateMap = [];

    public function __construct(Collection $payrollList)
    {
        $this->payrollList = $payrollList;
        $this->rateMap = Rate::query()
            ->pluck('minimum', 'code')
            ->all();
    }

    public function headings(): array
    {
        return [
            "Full Name",
            "Position",
            "Tax Type",
            "Home Branch",
            "Current Branch",
            "Employee Number",
            "Status",
            "On Maternity Leave",
            "Mancom",
            "Payment Type",
            "On Hold",
            "Holding Date",
            "Bank Description",
            "Bank Number",
            "Monthly Rate",
            "Schedule Type",
            "Daily Rate",
            "Region",
            "Separation Date"
        ];
    }

    public function collection()
    {
        return $this->payrollList->map(function ($list) {

            $bank = $list->current_bank_details;
            $position = $list->employee->current_position?->description ?? null;
            $isOic = ($list->employee->oic_status ?? null) === "OIC";
            $latestOic = ($list->employee->latest_position?->oic_assignment ?? null) === "Yes";
            if ($position && ($isOic || $latestOic) && !str_ends_with($position, " (OIC)")) {
                $position = "{$position} (OIC)";
            }
            $homeBranch = $list->employee->home_branch?->short_name ?? $list->employee->home_branch?->name ?? null;
            $currentBranch = $list->employee->current_branch?->short_name ?? $list->employee->current_branch?->name ?? null;
            $bankNumber = $bank?->bank_number ? (string) $bank->bank_number : null;

            $position = $this->normalizeValue($position);
            $homeBranch = $this->normalizeValue($homeBranch);
            $currentBranch = $this->normalizeValue($currentBranch);
            $bankNumber = $this->normalizeValue($bankNumber);
            $scheduleName = $list->employee->schedule?->schedule_type?->name ?? null;
            $scheduleType = null;

            $divisor = 26;
            if ($scheduleName) {
                if (stripos($scheduleName, "compressed") !== false) {
                    $scheduleType = "Compressed";
                    $divisor = 22;
                } elseif (stripos($scheduleName, "normal") !== false) {
                    $scheduleType = "Normal";
                } else {
                    $scheduleType = $scheduleName;
                }
            }

            $dailyRate = $list->salary_amount / $divisor;
            $scheduleType = $this->normalizeValue($scheduleType);
            $region = $list->employee->current_branch?->location ?? $list->employee->current_branch?->region ?? null;
            $region = $this->normalizeValue($region);
            $separationDate = $this->normalizeValue($list->employee->separation_date ?? null);

            return [
                $list->employee->full_name,
                $position,
                $list->employee->tax_type,
                $homeBranch,
                $currentBranch,
                $list->employee->employee_number,
                $list->is_included ? "Included" : "Excluded",
                $list->is_on_maternity_leave ? "Yes" : "No",
                $list->is_mancom ? "Yes" : "No",
                $list->is_bank ? "Bank" : "Cash",
                $list->is_hold ? "Yes" : "No",
                $list->holded_at ?? null,
                $bank?->bank_account_type?->description ?? null,
                $bankNumber,
                number_format($list->salary_amount, 2),
                $scheduleType,
                number_format($dailyRate, 2),
                $region,
                $separationDate
            ];
        });
    }

    private function normalizeValue($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === "" || strtolower($trimmed) === "undefined" || strtolower($trimmed) === "null") {
                return null;
            }
        }

        return $value;
    }

    public function columnFormats(): array
    {
        return [
            'M' => NumberFormat::FORMAT_TEXT
        ];
    }
}
