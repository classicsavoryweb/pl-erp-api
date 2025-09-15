<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class LastPaySetupExport implements FromCollection, WithHeadings
{
    protected Collection $last_pay_setup;

    public function __construct(Collection $last_pay_setup)
    {
        $this->last_pay_setup = $last_pay_setup;
    }

    public function collection()
    {
        return $this->last_pay_setup->map(function ($unique_last_pay_setup) {
            if ($unique_last_pay_setup->id === 1) {
                $lastPayName = "13th Month";
            } elseif ($unique_last_pay_setup->id === 2) {
                $lastPayName = "SL/VL";
            } elseif ($unique_last_pay_setup->id === 3) {
                $lastPayName = "Tax Refund";
            } elseif ($unique_last_pay_setup->id === 4) {
                $lastPayName = "Incentives (HO Setup)";
            } elseif ($unique_last_pay_setup->id === 5) {
                $lastPayName = "Sales Incentives";
            } elseif ($unique_last_pay_setup->id === 6) {
                $lastPayName = "Meal Allowance";
            } elseif ($unique_last_pay_setup->id === 7) {
                $lastPayName = "Incentives (Store Setup)";
            } elseif ($unique_last_pay_setup->id === 8) {
                $lastPayName = "SSS Loans (Amortization Setup)";
            } elseif ($unique_last_pay_setup->id === 9) {
                $lastPayName = "SSS Loans (For Remittance Setup)";
            } else {
                $lastPayName = "";
            }

            return [
                $lastPayName,
                $unique_last_pay_setup->debit_id,
                $unique_last_pay_setup->credit_id,
                $unique_last_pay_setup->setup_type,
                $unique_last_pay_setup->vendor_id,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Last Pay Name',
            'Debit Account',
            'Credit Account',
            'Setup Type',
            'Vendor ID',
        ];
    }
}
