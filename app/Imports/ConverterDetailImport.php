<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\ConverterDetail;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ConverterDetailImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        foreach ($collection as $trans) {
            $employee = Employee::where("employee_number", $trans["employee_id"])->first();

            if ($employee) {
                $bankAccount = $employee->latest_bank_details;
                $branch = Branch::where("short_name", $trans["branch_code"])->first();

                ConverterDetail::create([
                    "employee_id" => $employee->id,
                    "branch_id" => $branch->id,
                    "amount" => $trans["amount"],
                    "transaction_type_id" => $trans["transaction_id"],
                    "terms" => 0,
                    "remarks" => $trans["remarks"],
                    "added_by" => $this->userId,
                    "status" => "Pending",
                    "source" => "Manual",
                    "bank_account_number" => $bankAccount->bank_number
                ]);
            }
        }
    }
}
