<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class ContributionAndTaxExport implements FromCollection, WithHeadings
{
    protected Collection $contributions_and_tax;

    public function __construct(Collection $contributions_and_tax)
    {
        $this->contributions_and_tax = $contributions_and_tax;
    }

    public function collection()
    {
        return $this->contributions_and_tax->map(function ($item) {
            if (in_array($item->id, [1, 2, 3, 7])) {
                $contributionType = "EMPLOYEE CONTRIBUTION";
            } elseif (in_array($item->id, [4, 5, 6, 8])) {
                $contributionType = "EMPLOYER CONTRIBUTION";
            } else {
                $contributionType = "";
            }

            if (in_array($item->id, [1, 4])) {
                $contributionName = "Social Security System";
            } elseif (in_array($item->id, [2, 5])) {
                $contributionName = "Philippines Health Insurance Corporation";
            } elseif (in_array($item->id, [3, 6])) {
                $contributionName = "Home Development Mutual Fund";
            } elseif ($item->id === 7) {
                $contributionName = "Withholding Tax";
            } elseif ($item->id === 8) {
                $contributionName = "Non-Taxable Adjustment";
            } else {
                $contributionName = "";
            }

            return [
                $contributionType,
                $contributionName,
                $item->vendor_id,
                $item->debit_id,
                $item->credit_id,
                $item->vendor_name,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Contribution Type',
            'Contribution Name',
            'Vendor Account',
            'Debit Account',
            'Credit Account',
            'Vendor Name',
        ];
    }
}
