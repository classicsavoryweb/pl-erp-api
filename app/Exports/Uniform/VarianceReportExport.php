<?php

namespace App\Exports\Uniform;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VarianceReportExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $variances)
    {
    }

    public function collection()
    {
        return $this->variances->map(fn ($record) => [
            $record->variance_no,
            $record->physicalCount?->physical_count_no,
            optional($record->begin_date)->format('Y-m-d'),
            optional($record->ending_date)->format('Y-m-d'),
            $record->status,
            $record->prepared_by,
            optional($record->created_at)->format('Y-m-d H:i:s'),
            $record->reviewed_by,
            optional($record->reviewed_at)->format('Y-m-d H:i:s'),
            $record->posted_by,
            optional($record->posted_at)->format('Y-m-d H:i:s'),
            $record->cancelled_by,
            optional($record->cancelled_at)->format('Y-m-d H:i:s'),
            $record->cancelled_reason,
        ]);
    }

    public function headings(): array
    {
        return [
            'Variance No.',
            'Physical Count No.',
            'Begin Date',
            'Ending Date',
            'Status',
            'Prepared By',
            'Prepared Date',
            'Reviewed By',
            'Reviewed Date',
            'Posted By',
            'Posted Date',
            'Cancelled By',
            'Cancelled Date',
            'Cancel Reason',
        ];
    }
}
