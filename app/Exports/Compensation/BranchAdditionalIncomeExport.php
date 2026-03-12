<?php

namespace App\Exports\Compensation;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BranchAdditionalIncomeExport implements FromCollection, WithHeadings
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows->map(function ($row) {
            $amount = $row["amount"] ?? 0;
            if ($amount === null || $amount === '' || (is_numeric($amount) && (float) $amount == 0.0)) {
                $amount = "0.00";
            }

            return [
                $row["branch_number"] ?? "",
                $row["branch_name"] ?? "",
                $amount,
                $row["pay_date"] ?? "",
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Branch Number",
            "Branch Name",
            "Amount",
            "Payment Date",
        ];
    }
}
