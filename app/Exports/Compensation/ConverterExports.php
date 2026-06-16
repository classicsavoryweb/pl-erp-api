<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ConverterExports implements WithMultipleSheets
{
    protected Collection $transactions;

    public function __construct(Collection $transactions)
    {
        $this->transactions = $transactions;
    }
    
    public function sheets(): array
    {
        return [
            new ConverterDetailsExport($this->transactions),
            new ConverterSampleExport($this->transactions)
        ];
    }
}

