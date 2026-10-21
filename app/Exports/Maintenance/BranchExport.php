<?php

namespace App\Exports\Maintenance;

use App\Models\Rate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Database\Eloquent\Collection;

class BranchExport implements FromCollection, WithHeadings, WithTitle
{

    protected Collection $branches;
    protected array $minimumRates;

    public function __construct(Collection $branches)
    {
        $this->branches = $branches;
        $this->minimumRates = Rate::whereIn("code", $branches->pluck("rate_code")->filter()->unique())
            ->pluck("minimum", "code")
            ->all();
    }

    public function collection()
    {
        return $this->branches->map(function ($branch) {
            return [
                $branch->branch_number,
                $branch->name,
                $branch->short_name,
                $branch->type,
                $branch->date_open,
                $branch->location,
                $branch->company,
                $branch->branch_email,
                $branch->address,
                $branch->rate_code,
                $this->minimumRates[$branch->rate_code] ?? '',
                $branch->branch_head,
                $branch->area_head,
                $branch->lab_head,
                $branch->lab_head_2,
                $branch->mancom,
                $branch->travel_time,
                $branch->device_name,
                $branch->status,
                $branch->expense_debit,
                $branch->expense_credit,
                $branch->advance_debit,
                $branch->advance_credit,
                $branch->ho_13th_debit,
                $branch->ho_13th_debit_1,
                $branch->ho_13th_credit,
                $branch->branch_ho_13th_debit,
                $branch->branch_ho_13th_debit_1,
                $branch->branch_ho_13th_credit,
                $branch->branch_ho_stat_debit,
                $branch->branch_ho_stat_credit,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Branch Number',
            'Branch Name',
            'Short Name',
            'Type',
            'Date Opened',
            'Location',
            'Company',
            'Branch Email',
            'Branch Address',
            'Rate Code',
            'Minimum Rate',
            'Branch Head',
            'Area Head',
            'Lab Head',
            'Lab Head 2',
            'Mancom',
            'Travel Time',
            'Device Name',
            'Status',
            'Expense Account (Debit)',
            'Expense Account (Credit)',
            'Advance Account (Debit)',
            'Advance Account (Credit)',
            '13th, HO/HO (Debit)',
            '13th, HO/HO (Debit 1)',
            '13th, HO/HO (Credit)',
            '13th, Branch/HO (Debit)',
            '13th, Branch/HO (Debit 1)',
            '13th, Branch/HO (Credit)',
            'Statutory, Branch/HO (Debit)',
            'Statutory, Branch/HO (Credit)',
        ];
    }

    public function title(): string
    {
        return 'Branches';
    }
}
