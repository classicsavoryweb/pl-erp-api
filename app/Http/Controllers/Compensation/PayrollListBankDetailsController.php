<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Exports\Compensation\PayrollListBankDetailExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Models\PayrollList;

class PayrollListBankDetailsController extends Controller
{
    public function exportBankDetails(Request $request)
    {
        $payrollbankdetails = PayrollList::with(['employee.bank_details.bank_account_type'])->get();
        return Excel::download(new PayrollListBankDetailExport($payrollbankdetails),
            'BANK-DETAILS-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}

?>