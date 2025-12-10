<?php

namespace App\Exports\Reports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportsThirteenthMonthMonthlyAllocationExport implements WithMultipleSheets
{
    protected Collection $employeesMonthlyAllocationData;
    protected Collection $branchesMonthlyAllocationData;

    public function __construct(Collection $employeesMonthlyAllocationData, $branchesMonthlyAllocationData)
    {
        $this->employeesMonthlyAllocationData = $employeesMonthlyAllocationData;
        $this->branchesMonthlyAllocationData = $branchesMonthlyAllocationData;
    }

    public function sheets(): array
    {
        return [
            new EmployeeThirteenthMonthAllocationSheet($this->employeesMonthlyAllocationData),
            new BranchThirteenthMonthAllocationSheet($this->branchesMonthlyAllocationData),
        ];
    }
}
