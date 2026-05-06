<?php

namespace App\Exports\Maintenance;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BranchesWorkbookExport implements WithMultipleSheets
{
    protected Collection $branches;
    protected array $oicHistoryRows;
    protected int $maxHistoryColumns;

    public function __construct(Collection $branches, array $oicHistoryRows, int $maxHistoryColumns)
    {
        $this->branches = $branches;
        $this->oicHistoryRows = $oicHistoryRows;
        $this->maxHistoryColumns = $maxHistoryColumns;
    }

    public function sheets(): array
    {
        return [
            new BranchExport($this->branches),
            new BranchOicHistoryExport($this->oicHistoryRows, $this->maxHistoryColumns),
        ];
    }
}
