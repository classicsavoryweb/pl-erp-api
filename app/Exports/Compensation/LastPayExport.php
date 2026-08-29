<?php

namespace App\Exports\Compensation;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LastPayExport implements FromCollection, WithHeadings
{
    protected Collection $lastPays;
    protected int $maxDeductions;

    public function __construct(Collection $lastPays)
    {
        $this->lastPays = $lastPays;
        $this->maxDeductions = $lastPays->max(function ($lastPay) {
            return $lastPay->deductions ? $lastPay->deductions->count() : 0;
        }) ?? 0;
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
            $deductions = $lastPay->deductions ?? collect();
            $createdBy = $createdActivity?->causer
                ? (
                    $createdActivity->causer->proper_full_name
                    ?? $createdActivity->causer->full_name
                    ?? $createdActivity->causer->name
                    ?? ''
                )
                : '';
            $postedBy = $postedActivity?->causer
                ? (
                    $postedActivity->causer->proper_full_name
                    ?? $postedActivity->causer->full_name
                    ?? $postedActivity->causer->name
                    ?? ''
                )
                : '';

            $row = [
                $lastPay->last_pay_number,
                $lastPay->employee->full_name ?? '',
                $lastPay->employee->employee_number ?? '',
                $lastPay->employee->current_position->description ?? '',
                $lastPay->employee->current_branch->short_name ?? '',
                $lastPay->last_pay,
                $lastPay->status,
                optional($lastPay->created_at)->format('Y-m-d'),
                $createdBy,
                optional($datePosted)->format('Y-m-d'),
                $postedBy,
            ];

            for ($i = 0; $i < $this->maxDeductions; $i++) {
                $deduction = $deductions->values()->get($i);
                $row[] = $deduction
                    ? ($deduction->deduction->description ?? $deduction->reference_doc ?? '')
                    : '';
                $row[] = $deduction
                    ? (
                        ($deduction->amount === null || $deduction->amount === '')
                            ? 0.0
                            : (float) str_replace(',', '', (string) $deduction->amount)
                    )
                    : '';
            }

            return $row;
        });
    }

    public function headings(): array
    {
        $headings = [
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

        for ($i = 1; $i <= $this->maxDeductions; $i++) {
            $headings[] = "Deduction {$i}";
            $headings[] = "Amount {$i}";
        }

        return $headings;
    }
}
