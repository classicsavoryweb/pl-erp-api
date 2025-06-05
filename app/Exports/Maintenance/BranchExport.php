<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class BranchExport implements FromCollection, WithHeadings
{

    protected Collection $branches;

    public function __construct(Collection $branches)
    {
        $this->branches = $branches;
    }

    public function collection()
    {
        return $this->branches->map(function ($branch) {
            return [
                $branch->name,
                $branch->short_name,
                $branch->date_open,
                $branch->location,
                $branch->company,
                $branch->branch_email,
                $branch->rate_code,
                $branch->branch_head,
                $branch->group_head,
                $branch->kitchen_oic,
                $branch->mancom,
                $branch->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Branch Name',
            'Short Name',
            'Date Opened',
            'Location',
            'Company',
            'Rate Code',
            'Branch Email',
            'Branch OIC',
            'Group Head',
            'Kitchen OIC',
            'Mancom',
            'Status',
        ];
    }
}
