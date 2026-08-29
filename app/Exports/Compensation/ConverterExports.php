<?php

namespace App\Exports\Compensation;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ConverterExports implements WithMultipleSheets
{
    protected Collection $transactions;
    protected $converterMainId;

    public function __construct(Collection $transactions, $converterMainId)
    {
        $this->transactions = $transactions;
        $this->converterMainId = $converterMainId;
    }
    
    public function sheets(): array
    {
        return [
            new ConverterDetailsExport($this->transactions),
            new ConverterSampleExport($this->transactions, $this->converterMainId)
        ];
    }
}

