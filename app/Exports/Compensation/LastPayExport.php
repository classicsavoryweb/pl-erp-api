<?php

namespace App\Exports\Compensation;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LastPayExport implements FromCollection, WithHeadings
{
    protected Collection $lastPays;

    public function __construct(Collection $lastPays)
    {
        $this->lastPays = $lastPays;
    }

    public function collection()
    {
        return $this->lastPays->map(function ($lastPay) {
            $createdActivity = $lastPay->activities
                ? $lastPay->activities->first(function ($activity) {
                    return $activity->event === 'created' || $activity->description === 'created';
                })
                : null;
            $postedActivity = $lastPay->activities
                ? $lastPay->activities->first(function ($activity) {
                    return $activity->event === 'posted' || $activity->description === 'posted';
                })
                : null;
            $datePosted = $postedActivity?->created_at;
            if (!$datePosted && $lastPay->status === 'Posted') {
                $datePosted = $lastPay->updated_at;
            }

            return [
                $lastPay->last_pay_number,
                $lastPay->employee->full_name ?? '',
                $lastPay->employee->employee_number ?? '',
                $lastPay->employee->current_position->description ?? '',
                $lastPay->employee->current_branch->short_name ?? '',
                $lastPay->last_pay,
                $lastPay->status,
                optional($lastPay->created_at)->format('Y-m-d'),
                $this->activityUserName($createdActivity),
                optional($datePosted)->format('Y-m-d'),
                $this->activityUserName($postedActivity),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Last Pay Number',
            'Employee Name',
            'Employee Number',
            'Position',
            'Branch',
            'Last Pay',
            'Status',
            'Date Created',
            'Created By',
            'Date Posted',
            'Posted By',
        ];
    }

    private function activityUserName($activity): string
    {
        if (!$activity?->causer) {
            return '';
        }

        return $activity->causer->proper_full_name
            ?? $activity->causer->full_name
            ?? $activity->causer->name
            ?? '';
    }
}
