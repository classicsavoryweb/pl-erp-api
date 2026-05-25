<?php

namespace App\Exports\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class BankAccountHistoryExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithCustomValueBinder
{

    protected Collection $employees;
    protected $maxBanks = 0;

    public function __construct (Collection $employees) {
        $this->employees = $employees;

        $this->maxBanks = $employees->max(function ($employee) {
            return $employee->bank_details->count();
        });
    }
    /**
    * @return \Illuminate\Support\Collection
    */

    public function bindValue(Cell $cell, $value)
    {
        $cell->setValueExplicit(
            $value,
            DataType::TYPE_STRING
        );

        return true;
    }
    public function collection()
    {
        $employees = $this->employees->map(function ($employee) {
            $row = [
                $employee->full_name,
                (string) $employee->employee_number,
            ];

            foreach ($employee->bank_details as $bank) {
                $row[] = (string) $bank->bank_number;
                $row[] = Carbon::parse($bank->created_at)->format("m/d/Y");
                $row[] = (string) $bank->remarks;
            }

            return $row;
        });

        return $employees;
    }

    public function headings(): array
    {
        $headings = [
            'Employee Name',
            'Employee Number',
        ];

        for ($i = 1; $i <= $this->maxBanks; $i++) {
            $headings[] = "Bank {$i} Account Number";
            $headings[] = "Bank {$i} Date Added";
            $headings[] = "Bank {$i} Remarks";
        }

        return $headings;
    }
}
