<?php

namespace App\Exports\Maintenance;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BranchOicHistoryExport implements FromCollection, WithHeadings, WithTitle
{
    protected array $rows;
    protected int $maxHistoryColumns;

    public function __construct(array $rows, int $maxHistoryColumns)
    {
        $this->rows = $rows;
        $this->maxHistoryColumns = $maxHistoryColumns;
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(function ($row) {
            $historyEntries = $row['history_entries'] ?? [];
            $historyEntries = array_values(array_reverse($historyEntries));
            $historyEntries = array_pad($historyEntries, $this->maxHistoryColumns, null);

            return array_merge([
                $row['branch'] ?? null,
                $row['branch_code'] ?? null,
                $row['position'] ?? null,
            ], $historyEntries);
        });
    }

    public function headings(): array
    {
        return array_merge(
            ['Branch', 'Branch Code', 'Position'],
            array_fill(0, $this->maxHistoryColumns, '')
        );
    }

    public function title(): string
    {
        return 'Branch OIC History';
    }
}
