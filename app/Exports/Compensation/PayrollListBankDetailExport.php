<?php

namespace App\Exports\Compensation;

use App\Models\PayrollList;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class PayrollListBankDetailExport implements FromCollection, WithHeadings
{
    protected Collection $payrollbankdetails;

    public function __construct(Collection $payrollbankdetails)
    {
        $this->payrollbankdetails = $payrollbankdetails;
    }
    
    public function collection()
    {
        return $this->payrollbankdetails->map(function ($payrollbanks) {

            $cashCard = $payrollbanks->employee->bank_details->filter(fn($bd) => $bd->bank_account_type->description === 'Cash Card')->first();
            $savingsAccount = $payrollbanks->employee->bank_details->filter(fn($bd) => $bd->bank_account_type->description === 'Savings Account')->first();
            $bdoPay = $payrollbanks->employee->bank_details->filter(fn($bd) => $bd->bank_account_type->description === 'BDO Pay')->first();

            return [
                $payrollbanks->employee->full_name,
                $payrollbanks->employee->employee_number,
                $cashCard ? $cashCard->bank_account_type->description : '', 
                $cashCard ? "'" . $cashCard->bank_number : '',
                $cashCard ? $cashCard->bank_account_type->created_at->format('Y-m-d H:i:s') : '',
                $savingsAccount ? $savingsAccount->bank_account_type->description : '', 
                $savingsAccount ? $savingsAccount->bank_number : '',
                $savingsAccount ? $savingsAccount->bank_account_type->created_at->format('Y-m-d H:i:s') : '',
                $bdoPay ? $bdoPay->bank_account_type->description : '', 
                $bdoPay ? $bdoPay->bank_number : '',
                $bdoPay ? $bdoPay->bank_account_type->created_at->format('Y-m-d H:i:s') : '',
            ];
        });
    }

    public function headings(): array
    {
        return 
        [
            'Employee Name', 
            'Employee Number', 
            'Bank Detail 1', 
            'Bank Number 1', 
            'Date Created At',
            'Bank Detail 2', 
            'Bank Number 2', 
            'Date Created At',
            'Bank Detail 3',
            'Bank Number 3',
            'Date Created At'
        ];
    }
    
}
