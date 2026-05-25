<?php

namespace App\Exports\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BankAccountHistoryExport implements FromCollection, WithHeadings
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
    public function collection()
    {
        $employees = $this->employees->map(function ($employee) {
            $row = [
                $employee->full_name,
                (string) $employee->employee_number,
            ];

            foreach ($employee->bank_details as $bank) {
                $row[] = "'". $bank->bank_number;
                $row[] = Carbon::parse($bank->created_at)->toDateString();
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
