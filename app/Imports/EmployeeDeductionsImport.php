<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EmployeeDeductionsImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public ?Collection $rows = null;

    public function headingRow(): int
    {
        return 1;
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $collection)
    {
        $this->rows = $collection;
    }
}
