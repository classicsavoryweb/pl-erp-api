<?php

namespace App\Exports\Uniform;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WeightedAverageExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $weightedAverages)
    {
    }

    public function collection()
    {
        return $this->weightedAverages->map(fn ($weightedAverage) => [
            $weightedAverage->weighted_id,
            optional($weightedAverage->date_posted)->format('Y-m-d') ?? $weightedAverage->date_posted,
            optional($weightedAverage->date_from)->format('Y-m-d') ?? $weightedAverage->date_from,
            optional($weightedAverage->date_to)->format('Y-m-d') ?? $weightedAverage->date_to,
            $weightedAverage->tag,
            $weightedAverage->status,
            $weightedAverage->overall_remarks,
            $weightedAverage->prepared_by,
            $this->formatDate($weightedAverage->prepared_at),
            $weightedAverage->reviewed_by,
            $this->formatDate($weightedAverage->reviewed_at),
            $weightedAverage->posted_by,
            $this->formatDate($weightedAverage->posted_at),
            $weightedAverage->cancelled_by,
            $this->formatDate($weightedAverage->cancelled_at),
            $weightedAverage->cancel_reason,
        ]);
    }

    public function headings(): array
    {
        return [
            'WA Number',
            'Posting Date',
            'Date From',
            'Date To',
            'Tag',
            'Status',
            'Overall Remarks',
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

    private function formatDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        return is_object($value) && method_exists($value, 'format')
            ? $value->format('Y-m-d H:i:s')
            : date('Y-m-d H:i:s', strtotime((string) $value));
    }
}
