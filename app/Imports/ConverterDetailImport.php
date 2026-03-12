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
            $employee = Employee::with("current_branch")->where("employee_number", $trans["employee_id"])->first();

            if ($employee) {
                $branch = Branch::where("short_name", $trans["branch_code"])->first();

                if (!$branch) {
                    $branch = $employee->current_branch;
                }
                ConverterDetail::updateOrCreate([
                    "status" => "Pending",
                    "source" => "Manual",
                    "employee_id" => $employee->id,
                    "amount" => $trans["amount"],
                    "transaction_type_id" => $trans["transaction_id"],
                    "remarks" => $trans["remarks"],
                ],[
                    "terms" => 0,
                    "branch_id" => $branch->id ?? 0,
                    "added_by" => $this->userId,
                ]);
            }
        }
    }
}
