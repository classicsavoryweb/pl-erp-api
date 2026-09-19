<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class PettyCashApproverExport implements FromCollection, WithHeadings
{
    protected Collection $approvers;

    public function __construct(Collection $approvers)
    {
        $this->approvers = $approvers;
    }

    public function collection()
    {
        return $this->approvers->map(function ($app) {
            $employees = $app->employees->map(function ($emp) {
                return $emp->last_name . ', ' . $emp->first_name . ' (' . ($emp->pivot->min_amount ?? 0) . ' - ' . ($emp->pivot->max_amount ?? 0) . ')';
            })->implode("\n");

            $branches = $app->branches->map(function ($br) {
                return $br->name;
            })->implode(', ');

            return [
                $app->description,
                $employees,
                $branches,
                $app->use_same_range ? 'Yes' : 'No',
                $app->use_same_range ? $app->min_amount : 'N/A',
                $app->use_same_range ? $app->max_amount : 'N/A',
                ucfirst($app->status),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Description',
            'Employees (Limits)',
            'Branches',
            'Same Range for All',
            'Global Min Amount',
            'Global Max Amount',
            'Status',
        ];
    }
}
