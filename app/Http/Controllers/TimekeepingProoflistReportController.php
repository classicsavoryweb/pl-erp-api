<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cutoff;
use App\Models\TimeKeepingSummary;
use App\Exports\Reports\TimekeepingProoflistReportExport;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class TimekeepingProoflistReportController extends Controller
{
    public function exportTimeKeepingProoflistReport(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required',
            ]);

            $cutOff = Cutoff::where('status', 'active')->first();

            if ($request->type === 'HISTORY') {
                $cutOff = Cutoff::find($request->pay_date);
            }

            if (!$cutOff) {
                throw new Exception('Cutoff not found.');
            }

            $timeKeepingSummaries = TimeKeepingSummary::with([
                'employee',
                'branch',
            ])
            ->where('cut_off', $cutOff->cut_off)
            ->get();

            return Excel::download(
                new TimeKeepingProoflistReportExport($timeKeepingSummaries),
                'timekeeping_summary.xlsx',
                \Maatwebsite\Excel\Excel::XLSX
            );

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage(),
            ]);
        }
    }

}
