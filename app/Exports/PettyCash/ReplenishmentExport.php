<?php

namespace App\Exports\PettyCash;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReplenishmentExport implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $rows;
    protected array $headers;

    public function __construct(Collection $rows, array $headers)
    {
        $this->rows = $rows;
        $this->headers = $headers;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function title(): string
    {
        return 'Replenishments';
    }
}
