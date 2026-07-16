<?php

namespace App\Exports\Uniform;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class RequestAndIssuanceExport implements FromCollection, WithHeadings
{
    protected Collection $request;

    public function __construct(Collection $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        return $this->request->map(function ($request) {
            return [
                $request->id,
                $request->request_number,
                $request->employee_id,
                $request->employee_name,
                $request->issuance_type,
                $request->branch,
                $request->position,
                $request->date_hired,
                $request->date_requested,
                $request->date_reviewed,
                $request->date_posted,
                $request->date_cancelled,
                $request->total_amount,
                $request->status,
                $request->sent_email,
                $request->payroll_entry,
                $request->payroll_sent_date,
                $request->created_by,
                $request->reviewed_by,
                $request->posted_by,
                $request->cancelled_by,
                $request->cancel_reason,            
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Request Number',
            'Employee Number',
            'Employee Name',
            'Issuance Type',
            'Branch',
            'Position',
            'Date Hired',
            'Date Requested',
            'Date Reviewed',
            'Date Posted',
            'Date Cancelled',
            'Total Amount',
            'Status',
            'Sent Email',
            'Payroll Entry',
            'Payroll Sent Date',
            'Created By',
            'Reviewed By',
            'Posted By',
            'Cancelled By',
            'Cancel Reason',

        ];
    }
}
