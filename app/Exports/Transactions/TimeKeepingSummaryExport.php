<?php

namespace App\Exports\Transactions;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TimeKeepingSummaryExport implements WithMultipleSheets
{
    protected Collection $timekeeping_summary;
    protected $start_date;
    protected $end_date;

    public function __construct(Collection $timekeeping_summary, $start_date, $end_date)
    {
        $this->timekeeping_summary = $timekeeping_summary;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function sheets(): array
    {
        return [
            new TimeKeepingSummaryTemplateSheet(
                $this->timekeeping_summary,
                $this->start_date,
                $this->end_date
            ),
            new TimeKeepingSummaryOriginalSheet(
                $this->timekeeping_summary,
                $this->start_date,
                $this->end_date
            ),
        ];
    }
}
